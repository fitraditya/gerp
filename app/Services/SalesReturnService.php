<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use Illuminate\Support\Facades\DB;

/**
 * Customer returns (ERP-gap follow-up, Phase 4 of 4: COGS -> COA -> Purchasing -> Returns).
 * Supplier returns (sending goods back to a vendor) are a separate, out-of-scope concern —
 * see RFC.md Database Model "Purchasing / Accounts Payable" notes.
 *
 * Reverses the original sale using THAT LINE's snapshotted unit_price/unit_cost
 * (CheckoutService), not the product's current price/cost_price — a return must refund
 * what the customer actually paid and reverse the COGS actually recognized, regardless
 * of any price/cost change since the sale.
 */
class SalesReturnService
{
    public function __construct(
        private InventoryService $inventoryService,
        private LedgerService $ledgerService,
    ) {
    }

    /**
     * @param  array<array{product_id:int,quantity:int}>  $items
     */
    public function process(int $orderId, array $items, string $reason, int $actorId, ?string $refundMethod = null, ?\DateTimeInterface $occurredAt = null): SalesReturn
    {
        if (empty($items)) {
            throw new \RuntimeException('Return requires at least one item.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw new \RuntimeException('Reason must be at least 5 characters.');
        }

        return DB::transaction(function () use ($orderId, $items, $reason, $actorId, $refundMethod, $occurredAt) {
            $order = Order::withoutGlobalScope(WarehouseScope::class)->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== 'completed') {
                throw new \RuntimeException("Order is {$order->status}, cannot process a return against it.");
            }

            $orderLines = collect($order->items)->keyBy('product_id');

            // Sum every prior return against this order, per product, so a second
            // partial return can't push the total past what was actually sold.
            $alreadyReturned = SalesReturn::withoutGlobalScope(WarehouseScope::class)
                ->where('order_id', $order->id)
                ->get()
                ->flatMap(fn ($r) => collect($r->items))
                ->groupBy('product_id')
                ->map(fn ($lines) => $lines->sum('quantity'));

            $refundAmount = 0;
            $cogsReversal = 0;
            $lineItems = [];

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    throw new \RuntimeException('Return quantity must be positive.');
                }

                $orderLine = $orderLines->get($productId);
                if (!$orderLine) {
                    throw new \RuntimeException("Product [{$productId}] was not on order {$order->order_number}.");
                }

                $returnedSoFar = (int) ($alreadyReturned->get($productId) ?? 0);
                $available = $orderLine['quantity'] - $returnedSoFar;
                if ($qty > $available) {
                    throw new \RuntimeException("Cannot return {$qty}x product [{$productId}] — only {$available} eligible (already returned {$returnedSoFar}).");
                }

                $unitPrice = (float) $orderLine['unit_price'];
                $unitCost = (float) ($orderLine['unit_cost'] ?? 0);
                $refundSubtotal = $unitPrice * $qty;
                $costSubtotal = $unitCost * $qty;

                $refundAmount += $refundSubtotal;
                $cogsReversal += $costSubtotal;

                $lineItems[] = [
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'refund_subtotal' => $refundSubtotal,
                    'unit_cost' => $unitCost,
                    'cost_subtotal' => $costSubtotal,
                ];

                $product = Product::findOrFail($productId);
                $this->inventoryService->restock($product, $order->warehouse_id, $qty, $actorId, $occurredAt);
            }

            $return = SalesReturn::create([
                'return_number' => 'RET-'.uniqid(),
                'order_id' => $order->id,
                'warehouse_id' => $order->warehouse_id,
                'created_by' => $actorId,
                'reason' => $reason,
                'items' => $lineItems,
                'refund_amount' => $refundAmount,
                'cogs_reversal' => $cogsReversal,
                'refund_method' => $refundMethod ?? $order->payment_method,
                'status' => 'completed',
                'processed_at' => $occurredAt ?? now(),
            ]);

            $warehouse = Warehouse::withoutGlobalScope(WarehouseScope::class)->findOrFail($order->warehouse_id);
            $sourceCode = $return->refund_method === 'qris'
                ? 'QRIS_CLEARING'
                : LedgerService::drawerCode($warehouse->code);

            // Exact reversal of CheckoutService's sale posting (from:SALES_REVENUE,
            // to:drawer/QRIS) — direction flipped, same two accounts.
            $this->ledgerService->post(
                from: $sourceCode,
                to: 'SALES_REVENUE',
                amount: $refundAmount,
                type: 'return',
                description: "Return {$return->return_number} for {$order->order_number}",
                transactionable: $return,
                warehouseId: $order->warehouse_id,
                actorId: $actorId,
                occurredAt: $occurredAt,
            );

            // Exact reversal of CheckoutService's COGS posting (from:INVENTORY_ASSET,
            // to:COGS_EXPENSE). Skipped when the sold line had no recorded cost — same
            // "$0 cost, nothing to reverse" tolerance as the original sale.
            if ($cogsReversal > 0) {
                $this->ledgerService->post(
                    from: 'COGS_EXPENSE',
                    to: 'INVENTORY_ASSET',
                    amount: $cogsReversal,
                    type: 'return_cogs',
                    description: "Return COGS reversal {$return->return_number}",
                    transactionable: $return,
                    warehouseId: $order->warehouse_id,
                    actorId: $actorId,
                    occurredAt: $occurredAt,
                );
            }

            return $return;
        });
    }
}
