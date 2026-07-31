<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private InventoryService $inventoryService,
        private LedgerService $ledgerService,
    ) {
    }

    /**
     * @param  array{idempotency_key?:string,discount_id?:int,payment_method?:string,items:array<array{product_id:int,quantity:int}>}  $payload
     */
    public function process(array $payload, int $warehouseId, int $cashierId, ?\DateTimeInterface $occurredAt = null): Order
    {
        $idempotencyKey = $payload['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existing = Order::withoutGlobalScope(WarehouseScope::class)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            return $this->processFresh($payload, $warehouseId, $cashierId, $idempotencyKey, $occurredAt);
        } catch (\Illuminate\Database\QueryException $e) {
            // Concurrent retry with the same idempotency_key raced us to the unique index — return the winner's row.
            if ($idempotencyKey && str_contains($e->getMessage(), 'idempotency_key')) {
                return Order::withoutGlobalScope(WarehouseScope::class)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();
            }
            throw $e;
        }
    }

    private function processFresh(array $payload, int $warehouseId, int $cashierId, ?string $idempotencyKey, ?\DateTimeInterface $occurredAt = null): Order
    {
        return DB::transaction(function () use ($payload, $warehouseId, $cashierId, $idempotencyKey, $occurredAt) {
            $warehouse = Warehouse::withoutGlobalScope(WarehouseScope::class)->findOrFail($warehouseId);

            $items = $payload['items'] ?? [];
            if (empty($items)) {
                throw new \RuntimeException('Checkout requires at least one item.');
            }

            // Stable lock order across the whole cart to avoid deadlocking a concurrent
            // overlapping cart that locks the same SKUs in a different order.
            $sortedItems = collect($items)->sortBy('product_id')->values();

            $productIds = $sortedItems->pluck('product_id')->unique();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $discount = null;
            if (!empty($payload['discount_id'])) {
                $discount = Discount::lockForUpdate()->find($payload['discount_id']);
                if (!$discount || !$discount->isValid()) {
                    throw new \RuntimeException('Invalid or expired discount.');
                }
            }

            $subtotal = 0;
            $cogsTotal = 0;
            $lineItems = [];
            foreach ($sortedItems as $item) {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    throw new \RuntimeException("Product [{$item['product_id']}] not found.");
                }
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    throw new \RuntimeException('Item quantity must be positive.');
                }

                $unitPrice = (float) $product->price;
                $lineSubtotal = $unitPrice * $qty;
                $subtotal += $lineSubtotal;

                // Snapshotted at sale time: product.cost_price can change later, but this
                // order's margin must keep reflecting what it actually cost back then.
                // Null cost_price (unknown / donated stock) reads as 0, not an error.
                $unitCost = (float) ($product->cost_price ?? 0);
                $lineCost = $unitCost * $qty;
                $cogsTotal += $lineCost;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $lineSubtotal,
                    'unit_cost' => $unitCost,
                    'cost_subtotal' => $lineCost,
                ];
            }

            $discountAmount = $discount ? min($discount->calculateDiscount($subtotal), $subtotal) : 0;
            $total = $subtotal - $discountAmount;
            $grossProfit = $total - $cogsTotal;

            $order = Order::create([
                'order_number' => 'ORD-'.uniqid(),
                'idempotency_key' => $idempotencyKey,
                'warehouse_id' => $warehouseId,
                'cashier_id' => $cashierId,
                'discount_id' => $discount?->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'cogs_total' => $cogsTotal,
                'gross_profit' => $grossProfit,
                'payment_method' => strtolower($payload['payment_method'] ?? 'cash'),
                'status' => 'completed',
                'completed_at' => $occurredAt ?? now(),
                'items' => $lineItems,
            ]);

            $hasNegativeStock = false;
            foreach ($lineItems as $line) {
                $inventory = $this->inventoryService->lockAndDecrement($line['product_id'], $warehouseId, $line['quantity'], $order, $cashierId, $occurredAt);
                if ($inventory->quantity < 0) {
                    $hasNegativeStock = true;
                }
            }

            if ($hasNegativeStock) {
                $order->has_negative_stock_flag = true;
                $order->save();
            }

            if ($discount) {
                $discount->increment('usage_count');
            }

            $destinationCode = $order->payment_method === 'qris'
                ? 'QRIS_CLEARING'
                : LedgerService::drawerCode($warehouse->code);

            $this->ledgerService->post(
                from: 'SALES_REVENUE',
                to: $destinationCode,
                amount: (float) $total,
                type: 'sale',
                description: "Sale {$order->order_number}",
                transactionable: $order,
                warehouseId: $warehouseId,
                actorId: $cashierId,
                occurredAt: $occurredAt,
            );

            // Recognizes COGS against the inventory asset built up by InventoryService's
            // optional funding-source posting on receipt. Skipped for $0 cost (donated
            // stock, or cost_price never recorded) — nothing to move, and it keeps
            // COGS_EXPENSE/INVENTORY_ASSET untouched for sales that never funded inventory.
            if ($cogsTotal > 0) {
                $this->ledgerService->post(
                    from: 'INVENTORY_ASSET',
                    to: 'COGS_EXPENSE',
                    amount: (float) $cogsTotal,
                    type: 'cogs',
                    description: "COGS {$order->order_number}",
                    transactionable: $order,
                    warehouseId: $warehouseId,
                    actorId: $cashierId,
                    occurredAt: $occurredAt,
                );
            }

            if ($hasNegativeStock) {
                event(new \App\Events\NegativeStockFlag($order));
            }

            return $order->fresh();
        });
    }
}
