<?php

namespace Tests\Feature\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * PRD Story 3: Stock Opname. Actual schema is submit-then-verify (RFC deviation #12),
 * not the single-step flow in the RFC's own sequence diagram — inventory.quantity
 * is untouched until verifyOpname().
 */
class InventoryOpnameTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private InventoryService $inventory;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
        $this->inventory = app(InventoryService::class);
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create();
    }

    public function test_submit_records_pending_audit_without_touching_stock(): void
    {
        $supervisor = $this->makeUser('Supervisor', $this->warehouse->id);
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 5, $supervisor->id);
        // Sell it down to -3 (negative stock allowed per Story 2).
        $this->inventory->lockAndDecrement($this->product->id, $this->warehouse->id, 8);

        $audit = $this->inventory->submitOpname($this->product->id, $this->warehouse->id, 12, 'Intake sorting batch delayed from central hub', $supervisor->id);

        $this->assertEquals('pending', $audit->status);
        $this->assertEquals(-3, $audit->expected_qty);
        $this->assertEquals(12, $audit->actual_qty);
        $this->assertEquals(-3, $this->currentQty());
    }

    public function test_verify_applies_physical_qty_and_clears_negative_stock(): void
    {
        // PRD AC: -3 in system, physical count 12, resolves to 12 with variance +15.
        $supervisor = $this->makeUser('Supervisor', $this->warehouse->id);
        $manager = $this->makeUser('Manager');
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 5, $supervisor->id);
        $this->inventory->lockAndDecrement($this->product->id, $this->warehouse->id, 8);

        $audit = $this->inventory->submitOpname($this->product->id, $this->warehouse->id, 12, 'Intake sorting batch delayed from central hub', $supervisor->id);
        $verified = $this->inventory->verifyOpname($audit, $manager->id);

        $this->assertEquals('verified', $verified->status);
        $this->assertEquals(12, $this->currentQty());
        $this->assertEquals(15, $verified->actual_qty - $verified->expected_qty);
    }

    public function test_cannot_verify_an_already_resolved_audit(): void
    {
        $supervisor = $this->makeUser('Supervisor', $this->warehouse->id);
        $manager = $this->makeUser('Manager');
        $audit = $this->inventory->submitOpname($this->product->id, $this->warehouse->id, 10, 'Recount after event', $supervisor->id);
        $this->inventory->verifyOpname($audit, $manager->id);

        $this->expectException(\RuntimeException::class);
        $this->inventory->verifyOpname($audit, $manager->id);
    }

    private function currentQty(): int
    {
        return \App\Models\Inventory::withoutGlobalScope(WarehouseScope::class)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('quantity');
    }
}
