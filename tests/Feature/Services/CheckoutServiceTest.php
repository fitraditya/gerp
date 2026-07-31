<?php

namespace Tests\Feature\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private CheckoutService $checkout;
    private InventoryService $inventory;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();

        $this->checkout = app(CheckoutService::class);
        $this->inventory = app(InventoryService::class);

        $this->warehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $this->warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($this->warehouse->code), 'balance' => 0]);
        $this->product = Product::factory()->create(['price' => 10000]);
    }

    public function test_checkout_decrements_stock_and_posts_ledger(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 5, $cashier->id);

        $order = $this->checkout->process([
            'idempotency_key' => 'test-key-1',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ], $this->warehouse->id, $cashier->id);

        $this->assertEquals(30000, $order->total);
        $this->assertFalse($order->has_negative_stock_flag);
        $this->assertEquals(2, \App\Models\Inventory::withoutGlobalScope(\App\Scopes\WarehouseScope::class)
            ->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->value('quantity'));

        $drawer = CashAccount::where('code', LedgerService::drawerCode($this->warehouse->code))->first();
        $this->assertEquals(30000, $drawer->balance);
    }

    public function test_checkout_allows_negative_stock_and_flags_it(): void
    {
        // PRD Story 2 AC: item reads 0 in DB, sale still processes, quantity goes negative.
        $cashier = $this->makeUser('Staff', $this->warehouse->id);

        $order = $this->checkout->process([
            'idempotency_key' => 'test-key-negative',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->warehouse->id, $cashier->id);

        $this->assertTrue($order->has_negative_stock_flag);
        $this->assertEquals(-1, \App\Models\Inventory::withoutGlobalScope(\App\Scopes\WarehouseScope::class)
            ->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->value('quantity'));
    }

    public function test_checkout_is_idempotent_on_retry(): void
    {
        // PRD/RFC: POS terminals on flaky bazaar wifi retry submits — must not double-decrement.
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 10, $cashier->id);

        $payload = [
            'idempotency_key' => 'retry-key',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ];

        $first = $this->checkout->process($payload, $this->warehouse->id, $cashier->id);
        $second = $this->checkout->process($payload, $this->warehouse->id, $cashier->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(8, \App\Models\Inventory::withoutGlobalScope(\App\Scopes\WarehouseScope::class)
            ->where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->value('quantity'));
    }

    public function test_checkout_rejects_invalid_discount(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $inactive = Discount::factory()->create(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired discount.');

        $this->checkout->process([
            'idempotency_key' => 'bad-discount',
            'discount_id' => $inactive->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->warehouse->id, $cashier->id);
    }

    public function test_checkout_snapshots_unit_cost_and_computes_gross_profit(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        $this->inventory->receiveStock($product, $this->warehouse->id, 5, $cashier->id);

        $order = $this->checkout->process([
            'idempotency_key' => 'test-cogs-key',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ], $this->warehouse->id, $cashier->id);

        $this->assertEquals(12000, $order->cogs_total);
        $this->assertEquals(18000, $order->gross_profit);
        $this->assertEquals(4000, $order->items[0]['unit_cost']);
        $this->assertEquals(12000, $order->items[0]['cost_subtotal']);
    }

    public function test_checkout_treats_null_cost_price_as_zero(): void
    {
        // Donated stock commonly has no recorded acquisition cost.
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => null]);
        $this->inventory->receiveStock($product, $this->warehouse->id, 5, $cashier->id);

        $order = $this->checkout->process([
            'idempotency_key' => 'test-null-cost-key',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $this->warehouse->id, $cashier->id);

        $this->assertEquals(0, $order->cogs_total);
        $this->assertEquals(20000, $order->gross_profit);
    }

    public function test_qris_checkout_routes_to_qris_clearing_not_branch_drawer(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 5, $cashier->id);

        $this->checkout->process([
            'idempotency_key' => 'qris-key',
            'payment_method' => 'qris',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->warehouse->id, $cashier->id);

        $drawer = CashAccount::where('code', LedgerService::drawerCode($this->warehouse->code))->first();
        $qris = CashAccount::where('code', 'QRIS_CLEARING')->first();

        $this->assertEquals(0, $drawer->balance);
        $this->assertEquals(10000, $qris->balance);
    }
}
