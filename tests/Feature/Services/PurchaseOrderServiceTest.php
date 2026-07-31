<?php

namespace Tests\Feature\Services;

use App\Models\CashAccount;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * ERP-gap follow-up (Phase 3 of 4: COGS -> COA -> Purchasing -> Returns).
 * Purchasing: Supplier + PurchaseOrder, liability tracked against goods actually
 * received (not the full ordered value), settled via recordPayment().
 */
class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private PurchaseOrderService $service;
    private Warehouse $warehouse;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
        $this->service = app(PurchaseOrderService::class);
        $this->warehouse = Warehouse::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['price' => 15000, 'cost_price' => null]);
    }

    public function test_create_computes_subtotal_and_total_from_items(): void
    {
        $manager = $this->makeUser('Manager');

        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);

        $this->assertEquals('ordered', $po->status);
        $this->assertEquals(50000, $po->subtotal);
        $this->assertEquals(50000, $po->total);
        $this->assertEquals(0, $po->received_total);
        $this->assertEquals(0, $po->balance_due);
    }

    public function test_create_rejects_inactive_supplier(): void
    {
        $manager = $this->makeUser('Manager');
        $inactiveSupplier = Supplier::factory()->create(['is_active' => false]);

        $this->expectException(\RuntimeException::class);

        $this->service->create($inactiveSupplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_cost' => 1000],
        ], $manager->id);
    }

    public function test_create_rejects_duplicate_product_lines(): void
    {
        $manager = $this->makeUser('Manager');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('appears more than once');

        $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 1000],
            ['product_id' => $this->product->id, 'quantity' => 3, 'unit_cost' => 1200],
        ], $manager->id);
    }

    public function test_full_receive_updates_stock_cost_price_and_ap_liability(): void
    {
        $manager = $this->makeUser('Manager');
        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);

        $received = $this->service->receive($po, [
            ['product_id' => $this->product->id, 'quantity' => 10],
        ], $manager->id);

        $this->assertEquals('received', $received->status);
        $this->assertNotNull($received->received_at);
        $this->assertEquals(50000, $received->received_total);
        $this->assertEquals(50000, $received->balance_due);
        $this->assertEquals(10, $received->items[0]['quantity_received']);

        $this->assertEquals(5000, $this->product->fresh()->cost_price);

        $inventory = \App\Models\Inventory::withoutGlobalScope(\App\Scopes\WarehouseScope::class)
            ->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(10, $inventory->quantity);

        $this->assertEquals(50000, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
    }

    public function test_partial_receive_leaves_status_partially_received_and_remaining_outstanding(): void
    {
        $manager = $this->makeUser('Manager');
        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);

        $received = $this->service->receive($po, [
            ['product_id' => $this->product->id, 'quantity' => 4],
        ], $manager->id);

        $this->assertEquals('partially_received', $received->status);
        $this->assertNull($received->received_at);
        $this->assertEquals(20000, $received->received_total);
        $this->assertEquals(4, $received->items[0]['quantity_received']);
    }

    public function test_receive_rejects_quantity_exceeding_outstanding(): void
    {
        $manager = $this->makeUser('Manager');
        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 5, 'unit_cost' => 5000],
        ], $manager->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only 5 outstanding');

        $this->service->receive($po, [
            ['product_id' => $this->product->id, 'quantity' => 6],
        ], $manager->id);
    }

    public function test_record_payment_reduces_balance_due_and_posts_ledger(): void
    {
        $manager = $this->makeUser('Manager');
        $treasury = CashAccount::factory()->create(['code' => 'TREASURY', 'balance' => 100000]);

        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);
        $po = $this->service->receive($po, [['product_id' => $this->product->id, 'quantity' => 10]], $manager->id);

        $paid = $this->service->recordPayment($po, 'TREASURY', 30000, $manager->id);

        $this->assertEquals(30000, $paid->amount_paid);
        $this->assertEquals(20000, $paid->balance_due);
        $this->assertEquals(70000, $treasury->refresh()->balance);
    }

    public function test_record_payment_rejects_amount_exceeding_balance_due(): void
    {
        $manager = $this->makeUser('Manager');
        CashAccount::factory()->create(['code' => 'TREASURY', 'balance' => 100000]);

        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);
        $po = $this->service->receive($po, [['product_id' => $this->product->id, 'quantity' => 10]], $manager->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds outstanding balance due');

        $this->service->recordPayment($po, 'TREASURY', 999999, $manager->id);
    }

    public function test_cancel_blocked_once_anything_has_been_received(): void
    {
        $manager = $this->makeUser('Manager');
        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);
        $po = $this->service->receive($po, [['product_id' => $this->product->id, 'quantity' => 1]], $manager->id);

        $this->expectException(\RuntimeException::class);

        $this->service->cancel($po);
    }

    public function test_cancel_succeeds_before_anything_received(): void
    {
        $manager = $this->makeUser('Manager');
        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);

        $cancelled = $this->service->cancel($po);

        $this->assertEquals('cancelled', $cancelled->status);
    }

    public function test_ledger_stays_balanced_across_receive_and_payment(): void
    {
        $manager = $this->makeUser('Manager');
        CashAccount::factory()->create(['code' => 'TREASURY', 'balance' => 100000]);

        $po = $this->service->create($this->supplier->id, $this->warehouse->id, [
            ['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 5000],
        ], $manager->id);
        $po = $this->service->receive($po, [['product_id' => $this->product->id, 'quantity' => 10]], $manager->id);
        $this->service->recordPayment($po, 'TREASURY', 20000, $manager->id);

        $sum = Ledger::withoutGlobalScope(\App\Scopes\WarehouseScope::class)
            ->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();

        $this->assertEquals((float) $sum->d, (float) $sum->c);
    }
}
