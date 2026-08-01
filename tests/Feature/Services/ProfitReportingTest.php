<?php

namespace Tests\Feature\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\DashboardService;
use App\Services\InventoryReportService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * COGS/margin reporting: Product.cost_price -> Order.cogs_total/gross_profit
 * (snapshotted per CheckoutService) rolled up through InventoryReportService
 * (stock valued at cost) and DashboardService (period gross margin).
 */
class ProfitReportingTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private CheckoutService $checkout;
    private InventoryService $inventory;
    private Warehouse $warehouse;
    private Branch $branch;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();

        $this->checkout = app(CheckoutService::class);
        $this->inventory = app(InventoryService::class);

        $this->warehouse = Warehouse::factory()->create();
        $this->branch = Branch::factory()->create(['warehouse_id' => $this->warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($this->warehouse->code), 'balance' => 0]);
        $this->product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
    }

    public function test_stock_summary_values_inventory_at_cost_and_at_retail(): void
    {
        $manager = $this->makeUser('Manager', $this->warehouse->id);
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 10, $manager->id);

        $stock = app(InventoryReportService::class)->stockSummary($this->warehouse->id, now()->subDay());

        $this->assertEquals(100000, $stock['value_closing']); // 10 * retail price 10000
        $this->assertEquals(40000, $stock['value_closing_cost']); // 10 * cost 4000
    }

    public function test_stock_summary_treats_null_cost_price_as_zero(): void
    {
        $manager = $this->makeUser('Manager', $this->warehouse->id);
        $freeStock = Product::factory()->create(['price' => 5000, 'cost_price' => null]);
        $this->inventory->receiveStock($freeStock, $this->warehouse->id, 4, $manager->id);

        $stock = app(InventoryReportService::class)->stockSummary($this->warehouse->id, now()->subDay());

        $this->assertEquals(0, $stock['value_closing_cost']);
        $this->assertEquals(20000, $stock['value_closing']);
    }

    public function test_dashboard_summary_reports_cogs_gross_profit_and_margin(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $this->inventory->receiveStock($this->product, $this->warehouse->id, 5, $cashier->id);

        $this->checkout->process([
            'idempotency_key' => 'profit-key',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $this->warehouse->id, $cashier->id);

        $summary = app(DashboardService::class)->summary(
            now()->startOfDay(),
            now()->endOfDay(),
            $this->branch->id,
        );

        // 2 units * (10000 sell - 4000 cost) = 12000 gross profit on 20000 net revenue.
        $this->assertEquals(8000, $summary['total_cogs']);
        $this->assertEquals(12000, $summary['total_gross_profit']);
        $this->assertEquals(60.0, $summary['gross_margin_pct']);
    }
}
