<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    /**
     * Lock (or create-then-lock) the inventory row for a product/warehouse pair.
     * Must run inside an outer DB::transaction(). Bypasses WarehouseScope: this is
     * privileged service-layer stock mutation, not a user-facing scoped listing —
     * an actor whose own warehouse_id differs from $warehouseId (e.g. a Manager
     * transferring into a branch) must still be able to see/lock that row.
     */
    private function lockInventory(int $productId, int $warehouseId): Inventory
    {
        $query = fn () => Inventory::withoutGlobalScope(WarehouseScope::class)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate();

        $inventory = $query()->first();
        if ($inventory) {
            return $inventory;
        }

        try {
            return Inventory::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'quantity_reserved' => 0,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique (product_id, warehouse_id) race: another concurrent request inserted first.
            return $query()->firstOrFail();
        }
    }

    /**
     * Every inventory quantity change is logged here so period reports can reconstruct
     * point-in-time stock by working backward from the current (real-time) quantity:
     * qty_as_of(t) = current_qty - SUM(quantity_delta WHERE created_at > t).
     */
    private function logMovement(
        int $productId,
        int $warehouseId,
        string $type,
        int $quantityDelta,
        ?object $reference,
        ?int $actorId,
        ?\DateTimeInterface $occurredAt = null
    ): void {
        InventoryMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'created_by' => $actorId,
            'created_at' => $occurredAt ?? now(),
        ]);
    }

    /**
     * Decrement stock by $qty, allowing negative — used by CheckoutService inside its
     * own outer transaction. Caller is responsible for the surrounding DB::transaction().
     */
    public function lockAndDecrement(int $productId, int $warehouseId, int $qty, ?object $reference = null, ?int $actorId = null, ?\DateTimeInterface $occurredAt = null): Inventory
    {
        $inventory = $this->lockInventory($productId, $warehouseId);
        $inventory->quantity -= $qty;
        $inventory->save();

        $this->logMovement($productId, $warehouseId, 'SALE', -$qty, $reference, $actorId, $occurredAt);

        return $inventory;
    }

    /**
     * $fundingSource is optional and off by default — every existing call site (Filament's
     * "Receive Stock" action, opname/transfer flows, seeders, tests) keeps working
     * unchanged. When given, and the product has a positive cost_price, this also posts
     * a real double-entry ledger movement (debit INVENTORY_ASSET / credit $fundingSource)
     * so inventory-on-hand actually shows up as a valued asset instead of just a
     * reporting-only number (see InventoryReportService::stockSummary's value_*_cost).
     * Stock received without a funding source (e.g. donated goods, cost unknown) posts
     * no ledger entry — same "not every event needs an accounting entry" tolerance this
     * codebase already applies to negative stock.
     */
    public function receiveStock(
        Product $product,
        int $warehouseId,
        int $qty,
        ?int $actorId = null,
        ?\DateTimeInterface $occurredAt = null,
        string|CashAccount|null $fundingSource = null
    ): Inventory {
        return DB::transaction(function () use ($product, $warehouseId, $qty, $actorId, $occurredAt, $fundingSource) {
            $inventory = $this->lockInventory($product->id, $warehouseId);
            $inventory->quantity += $qty;
            $inventory->save();

            $this->logMovement($product->id, $warehouseId, 'RECEIVE', $qty, null, $actorId, $occurredAt);

            $unitCost = (float) ($product->cost_price ?? 0);
            if ($fundingSource !== null && $unitCost > 0) {
                $this->ledgerService->post(
                    from: $fundingSource,
                    to: 'INVENTORY_ASSET',
                    amount: $unitCost * $qty,
                    type: 'inventory_receipt',
                    description: "Receive {$qty}x {$product->sku} @ Rp{$unitCost}",
                    transactionable: $product,
                    warehouseId: $warehouseId,
                    actorId: $actorId,
                    occurredAt: $occurredAt,
                );
            }

            return $inventory;
        });
    }

    /**
     * @throws \RuntimeException if the destination warehouse is missing/inactive
     */
    public function transfer(Product $product, int $fromWarehouseId, int $toWarehouseId, int $qty, ?int $actorId = null, ?\DateTimeInterface $occurredAt = null): array
    {
        return DB::transaction(function () use ($product, $fromWarehouseId, $toWarehouseId, $qty, $actorId, $occurredAt) {
            $destination = Warehouse::withoutGlobalScope(WarehouseScope::class)->find($toWarehouseId);
            if (!$destination || !$destination->is_active) {
                throw new \RuntimeException('Destination warehouse invalid or inactive.');
            }

            // Lock rows in a stable order (lower product/warehouse pair first) so two
            // transfers moving stock in opposite directions between the same two
            // warehouses can't deadlock each other.
            $pairs = collect([
                ['role' => 'from', 'warehouse_id' => $fromWarehouseId],
                ['role' => 'to', 'warehouse_id' => $toWarehouseId],
            ])->sortBy('warehouse_id');

            $locked = [];
            foreach ($pairs as $pair) {
                $locked[$pair['role']] = $this->lockInventory($product->id, $pair['warehouse_id']);
            }

            $from = $locked['from'];
            $to = $locked['to'];

            $from->quantity -= $qty;
            $from->save();

            $to->quantity += $qty;
            $to->save();

            $transfer = InventoryTransfer::create([
                'transfer_number' => 'TF-'.uniqid(),
                'product_id' => $product->id,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'quantity' => $qty,
                'created_by' => $actorId,
                'status' => 'completed',
                'completed_at' => $occurredAt ?? now(),
            ]);

            $this->logMovement($product->id, $fromWarehouseId, 'TRANSFER_OUT', -$qty, $transfer, $actorId, $occurredAt);
            $this->logMovement($product->id, $toWarehouseId, 'TRANSFER_IN', $qty, $transfer, $actorId, $occurredAt);

            event(new \App\Events\InventoryTransferred($transfer));

            return compact('from', 'to', 'transfer');
        });
    }

    /**
     * Story 3 step 1: Supervisor/Manager/Admin submits a physical count. Recorded as
     * a pending audit — inventory.quantity is NOT mutated yet, matching this schema's
     * pending/verified InventoryAudit workflow (approval happens in verifyOpname()).
     */
    public function submitOpname(int $productId, int $warehouseId, int $actualQty, string $reasonLog, int $actorId): InventoryAudit
    {
        return DB::transaction(function () use ($productId, $warehouseId, $actualQty, $reasonLog, $actorId) {
            $inventory = $this->lockInventory($productId, $warehouseId);

            return InventoryAudit::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'created_by' => $actorId,
                'expected_qty' => $inventory->quantity,
                'actual_qty' => $actualQty,
                'notes' => $reasonLog,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Story 3 step 2: apply the counted quantity to inventory and close out the audit.
     * Also clears the negative-stock condition once corrected.
     */
    public function verifyOpname(InventoryAudit $audit, int $verifierId, ?\DateTimeInterface $occurredAt = null): InventoryAudit
    {
        return DB::transaction(function () use ($audit, $verifierId, $occurredAt) {
            $audit = InventoryAudit::withoutGlobalScope(WarehouseScope::class)->lockForUpdate()->findOrFail($audit->id);

            if ($audit->status !== 'pending') {
                throw new \RuntimeException('Audit already resolved.');
            }

            $inventory = $this->lockInventory($audit->product_id, $audit->warehouse_id);
            $delta = $audit->actual_qty - $inventory->quantity;
            $inventory->quantity = $audit->actual_qty;
            $inventory->save();

            $this->logMovement($audit->product_id, $audit->warehouse_id, 'OPNAME_ADJUST', $delta, $audit, $verifierId, $occurredAt);

            $audit->status = 'verified';
            $audit->verified_by = $verifierId;
            $audit->verified_at = now();
            $audit->save();

            return $audit;
        });
    }
}
