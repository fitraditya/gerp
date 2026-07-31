<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Scopes\WarehouseScope;
use Illuminate\Support\Facades\DB;

/**
 * Purchasing (ERP-gap follow-up, Phase 3 of 4: COGS -> COA -> Purchasing -> Returns).
 *
 * Liability is tracked against goods actually RECEIVED, not the full ordered value —
 * ordering 100 units doesn't create a debt until some of them arrive. `received_total`
 * accrues per receive() call; `balance_due` = received_total - amount_paid.
 */
class PurchaseOrderService
{
    public function __construct(
        private InventoryService $inventoryService,
        private LedgerService $ledgerService,
    ) {
    }

    /**
     * @param  array<array{product_id:int,quantity:int,unit_cost:float}>  $items
     */
    public function create(int $supplierId, int $warehouseId, array $items, int $actorId, ?string $notes = null, ?\DateTimeInterface $occurredAt = null): PurchaseOrder
    {
        if (empty($items)) {
            throw new \RuntimeException('Purchase order requires at least one item.');
        }

        $supplier = Supplier::findOrFail($supplierId);
        if (!$supplier->is_active) {
            throw new \RuntimeException('Supplier is inactive.');
        }

        $seenProductIds = [];
        $subtotal = 0;
        $lineItems = [];
        foreach ($items as $item) {
            $qty = (int) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];
            if ($qty <= 0) {
                throw new \RuntimeException('Item quantity must be positive.');
            }
            if ($unitCost < 0) {
                throw new \RuntimeException('Item unit cost cannot be negative.');
            }

            $product = Product::findOrFail($item['product_id']);

            // receive() keys its working copy of items by product_id (Product ||--o{
            // PURCHASE_ORDER_ITEMS is 1 row per product per PO) — a duplicate here would
            // silently collapse onto one line and strand the other's outstanding qty.
            if (in_array($product->id, $seenProductIds, true)) {
                throw new \RuntimeException("Product [{$product->id}] appears more than once — combine it into a single line.");
            }
            $seenProductIds[] = $product->id;

            $lineSubtotal = $qty * $unitCost;
            $subtotal += $lineSubtotal;

            $lineItems[] = [
                'product_id' => $product->id,
                'quantity_ordered' => $qty,
                'unit_cost' => $unitCost,
                'quantity_received' => 0,
            ];
        }

        return PurchaseOrder::create([
            'po_number' => 'PO-'.uniqid(),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouseId,
            'created_by' => $actorId,
            'status' => 'ordered',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'received_total' => 0,
            'amount_paid' => 0,
            'balance_due' => 0,
            'items' => $lineItems,
            'ordered_at' => $occurredAt ?? now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Receive some or all of a PO's outstanding lines into stock. Reuses
     * InventoryService::receiveStock()'s funding-source posting (debit
     * INVENTORY_ASSET / credit ACCOUNTS_PAYABLE) instead of duplicating ledger code —
     * the PO's negotiated unit_cost is written onto Product.cost_price first ("last
     * cost" costing) so that posting picks up the right amount.
     *
     * @param  array<array{product_id:int,quantity:int}>  $receivedItems
     */
    public function receive(PurchaseOrder $po, array $receivedItems, int $actorId, ?\DateTimeInterface $occurredAt = null): PurchaseOrder
    {
        if (empty($receivedItems)) {
            throw new \RuntimeException('Receive requires at least one item.');
        }

        return DB::transaction(function () use ($po, $receivedItems, $actorId, $occurredAt) {
            $po = PurchaseOrder::withoutGlobalScope(WarehouseScope::class)->lockForUpdate()->findOrFail($po->id);

            if (!in_array($po->status, ['ordered', 'partially_received'], true)) {
                throw new \RuntimeException("Purchase order is {$po->status}, cannot receive further stock.");
            }

            $lines = collect($po->items)->keyBy('product_id');
            $receivedValue = 0;

            foreach ($receivedItems as $received) {
                $productId = (int) $received['product_id'];
                $qty = (int) $received['quantity'];
                if ($qty <= 0) {
                    throw new \RuntimeException('Received quantity must be positive.');
                }

                $line = $lines->get($productId);
                if (!$line) {
                    throw new \RuntimeException("Product [{$productId}] is not on this purchase order.");
                }

                $remaining = $line['quantity_ordered'] - $line['quantity_received'];
                if ($qty > $remaining) {
                    throw new \RuntimeException("Cannot receive {$qty}x product [{$productId}] — only {$remaining} outstanding.");
                }

                $product = Product::findOrFail($productId);
                $product->cost_price = $line['unit_cost'];
                $product->save();

                $this->inventoryService->receiveStock(
                    $product,
                    $po->warehouse_id,
                    $qty,
                    $actorId,
                    $occurredAt,
                    fundingSource: 'ACCOUNTS_PAYABLE',
                );

                $line['quantity_received'] += $qty;
                $lines->put($productId, $line);
                $receivedValue += $qty * (float) $line['unit_cost'];
            }

            $allReceived = $lines->every(fn ($line) => $line['quantity_received'] >= $line['quantity_ordered']);

            $po->items = $lines->values()->all();
            $po->received_total = (float) $po->received_total + $receivedValue;
            $po->balance_due = (float) $po->received_total - (float) $po->amount_paid;
            $po->status = $allReceived ? 'received' : 'partially_received';
            if ($allReceived) {
                $po->received_at = $occurredAt ?? now();
            }
            $po->save();

            return $po;
        });
    }

    public function recordPayment(PurchaseOrder $po, string $cashAccountCode, float $amount, int $actorId, ?\DateTimeInterface $occurredAt = null): PurchaseOrder
    {
        if ($amount <= 0) {
            throw new \RuntimeException('Payment amount must be positive.');
        }

        return DB::transaction(function () use ($po, $cashAccountCode, $amount, $actorId, $occurredAt) {
            $po = PurchaseOrder::withoutGlobalScope(WarehouseScope::class)->lockForUpdate()->findOrFail($po->id);

            if ($amount > (float) $po->balance_due) {
                throw new \RuntimeException('Payment exceeds outstanding balance due.');
            }

            $this->ledgerService->post(
                from: $cashAccountCode,
                to: 'ACCOUNTS_PAYABLE',
                amount: $amount,
                type: 'purchase_payment',
                description: "Payment for {$po->po_number}",
                transactionable: $po,
                warehouseId: $po->warehouse_id,
                actorId: $actorId,
                occurredAt: $occurredAt,
            );

            $po->amount_paid = (float) $po->amount_paid + $amount;
            $po->balance_due = (float) $po->received_total - (float) $po->amount_paid;
            $po->save();

            return $po;
        });
    }

    /** Only an untouched PO (nothing received yet) can be cancelled. */
    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::withoutGlobalScope(WarehouseScope::class)->lockForUpdate()->findOrFail($po->id);

            if ($po->status !== 'ordered') {
                throw new \RuntimeException("Purchase order is {$po->status}, can only cancel an order with nothing received.");
            }

            $po->status = 'cancelled';
            $po->save();

            return $po;
        });
    }
}
