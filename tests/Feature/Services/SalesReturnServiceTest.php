<?php

namespace Tests\Feature\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Inventory;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Warehouse;
use App\Scopes\WarehouseScope;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use App\Services\SalesReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * ERP-gap analysis follow-up (Phase 4 of 4: COGS -> COA -> Purchasing -> Returns).
 * Customer returns reverse the ORIGINAL sale's snapshotted unit_price/unit_cost, not
 * the product's current price/cost_price.
 */
class SalesReturnServiceTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private SalesReturnService $service;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
        $this->service = app(SalesReturnService::class);
        $this->warehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $this->warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($this->warehouse->code), 'balance' => 0]);
    }

    private function sellFive(float $price = 10000, ?float $cost = 4000): array
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => $price, 'cost_price' => $cost]);
        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $cashier->id);

        $order = app(CheckoutService::class)->process([
            'idempotency_key' => 'return-test-'.uniqid(),
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->warehouse->id, $cashier->id);

        return [$order, $product, $cashier];
    }

    public function test_full_return_reverses_revenue_restocks_and_reverses_cogs(): void
    {
        [$order, $product, $cashier] = $this->sellFive();

        $return = $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 5],
        ], 'Wrong size, customer wants refund', $cashier->id);

        $this->assertEquals(50000, $return->refund_amount);
        $this->assertEquals(20000, $return->cogs_reversal);
        $this->assertEquals('cash', $return->refund_method);

        $inventory = Inventory::withoutGlobalScope(WarehouseScope::class)
            ->where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(5, $inventory->quantity); // sold 5 (->0), returned 5 (->5)

        $drawer = CashAccount::where('code', LedgerService::drawerCode($this->warehouse->code))->first();
        $this->assertEquals(0, $drawer->balance); // +50000 sale, -50000 refund

        // Sale posted -20000 to INVENTORY_ASSET (no funding source at receive, so it
        // went straight negative); the return's COGS reversal posts +20000 back — nets to 0.
        $this->assertEquals(0, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
        $this->assertEquals(0, CashAccount::where('code', 'COGS_EXPENSE')->value('balance'));
    }

    public function test_partial_return_leaves_remainder_sellable(): void
    {
        [$order, $product, $cashier] = $this->sellFive();

        $return = $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ], 'Two units defective', $cashier->id);

        $this->assertEquals(20000, $return->refund_amount);
        $this->assertEquals(8000, $return->cogs_reversal);

        $inventory = Inventory::withoutGlobalScope(WarehouseScope::class)
            ->where('product_id', $product->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(2, $inventory->quantity); // sold 5 (->0), returned 2 (->2)
    }

    public function test_cumulative_returns_cannot_exceed_original_quantity_sold(): void
    {
        [$order, $product, $cashier] = $this->sellFive();

        $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 3],
        ], 'First batch return', $cashier->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only 2 eligible (already returned 3)');

        $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 3],
        ], 'Second batch return', $cashier->id);
    }

    public function test_rejects_reason_shorter_than_five_characters(): void
    {
        [$order, $product, $cashier] = $this->sellFive();

        $this->expectException(\RuntimeException::class);

        $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'Bad', $cashier->id);
    }

    public function test_rejects_product_not_on_the_order(): void
    {
        [$order, $product, $cashier] = $this->sellFive();
        $otherProduct = Product::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('was not on order');

        $this->service->process($order->id, [
            ['product_id' => $otherProduct->id, 'quantity' => 1],
        ], 'Wrong item entirely', $cashier->id);
    }

    public function test_zero_cost_item_skips_cogs_reversal_posting(): void
    {
        [$order, $product, $cashier] = $this->sellFive(price: 10000, cost: null);

        $return = $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 5],
        ], 'Donated item returned', $cashier->id);

        $this->assertEquals(0, $return->cogs_reversal);
        $this->assertEquals(0, CashAccount::where('code', 'COGS_EXPENSE')->value('balance'));
        $this->assertEquals(0, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
    }

    public function test_qris_return_refunds_from_qris_clearing_not_drawer(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $cashier->id);

        $order = app(CheckoutService::class)->process([
            'idempotency_key' => 'return-qris-key',
            'payment_method' => 'qris',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $this->warehouse->id, $cashier->id);

        $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 2],
        ], 'QRIS payment refund', $cashier->id);

        $qris = CashAccount::where('code', 'QRIS_CLEARING')->first();
        $drawer = CashAccount::where('code', LedgerService::drawerCode($this->warehouse->code))->first();
        $this->assertEquals(0, $qris->balance); // +20000 sale, -20000 refund
        $this->assertEquals(0, $drawer->balance);
    }

    public function test_ledger_stays_balanced_across_sale_and_return(): void
    {
        [$order, $product, $cashier] = $this->sellFive();
        $this->service->process($order->id, [
            ['product_id' => $product->id, 'quantity' => 5],
        ], 'Full return balance check', $cashier->id);

        $sum = Ledger::withoutGlobalScope(WarehouseScope::class)
            ->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();

        $this->assertEquals((float) $sum->d, (float) $sum->c);
    }
}
