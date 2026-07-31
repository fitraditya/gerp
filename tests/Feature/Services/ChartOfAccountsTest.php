<?php

namespace Tests\Feature\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use App\Services\LedgerReportService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * ERP-gap follow-up (Phase 2 of 4: COGS -> COA -> Purchasing -> Returns).
 * Real double-entry for inventory value: InventoryService::receiveStock()'s optional
 * funding-source posting (debit INVENTORY_ASSET / credit funding source) and
 * CheckoutService's COGS posting (debit COGS_EXPENSE / credit INVENTORY_ASSET),
 * rolled up via LedgerReportService.
 */
class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private Warehouse $warehouse;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
        $this->warehouse = Warehouse::factory()->create();
        $this->branch = Branch::factory()->create(['warehouse_id' => $this->warehouse->id]);
        CashAccount::factory()->create(['code' => LedgerService::drawerCode($this->warehouse->code), 'balance' => 0]);
    }

    public function test_receive_stock_without_funding_source_posts_no_ledger_entry(): void
    {
        $manager = $this->makeUser('Manager', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);

        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $manager->id);

        $this->assertEquals(0, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
    }

    public function test_receive_stock_with_funding_source_posts_inventory_asset_entry(): void
    {
        $manager = $this->makeUser('Manager', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        $treasury = CashAccount::factory()->create(['code' => 'TREASURY', 'balance' => 100000]);

        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $manager->id, fundingSource: 'TREASURY');

        $this->assertEquals(20000, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
        $this->assertEquals(80000, $treasury->refresh()->balance);
    }

    public function test_checkout_posts_cogs_against_inventory_asset(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        $treasury = CashAccount::factory()->create(['code' => 'TREASURY', 'balance' => 100000]);

        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $cashier->id, fundingSource: 'TREASURY');

        app(CheckoutService::class)->process([
            'idempotency_key' => 'coa-checkout-1',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $this->warehouse->id, $cashier->id);

        // Receive posted 5*4000=20000 in; sale consumes 2*4000=8000 -> 12000 left on hand.
        $this->assertEquals(12000, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
        $this->assertEquals(8000, CashAccount::where('code', 'COGS_EXPENSE')->value('balance'));
    }

    public function test_checkout_skips_cogs_posting_for_zero_cost_stock(): void
    {
        // Donated stock: no cost_price recorded, no funding source used at receive.
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => null]);

        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $cashier->id);

        app(CheckoutService::class)->process([
            'idempotency_key' => 'coa-checkout-zero-cost',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $this->warehouse->id, $cashier->id);

        $this->assertEquals(0, CashAccount::where('code', 'COGS_EXPENSE')->value('balance'));
        $this->assertEquals(0, CashAccount::where('code', 'INVENTORY_ASSET')->value('balance'));
    }

    public function test_fund_pool_accounts_are_classified_as_expense_not_equity(): void
    {
        $this->assertEquals('expense', CashAccount::where('code', LedgerService::poolCode('HR'))->value('account_type'));
        $this->assertEquals('expense', CashAccount::where('code', 'COGS_EXPENSE')->value('account_type'));
        $this->assertEquals('asset', CashAccount::where('code', 'INVENTORY_ASSET')->value('account_type'));
    }

    public function test_trial_balance_groups_accounts_by_type(): void
    {
        $trialBalance = app(LedgerReportService::class)->trialBalance();

        $this->assertTrue($trialBalance->has('asset'));
        $this->assertTrue($trialBalance->has('expense'));
        $this->assertTrue($trialBalance->get('asset')->pluck('code')->contains('INVENTORY_ASSET'));
        $this->assertTrue($trialBalance->get('expense')->pluck('code')->contains('COGS_EXPENSE'));
    }

    public function test_profit_and_loss_reports_revenue_cogs_and_net_profit_for_period(): void
    {
        $cashier = $this->makeUser('Staff', $this->warehouse->id);
        $product = Product::factory()->create(['price' => 10000, 'cost_price' => 4000]);
        $treasury = CashAccount::factory()->create(['code' => 'TREASURY', 'balance' => 100000]);

        app(InventoryService::class)->receiveStock($product, $this->warehouse->id, 5, $cashier->id, fundingSource: 'TREASURY');

        app(CheckoutService::class)->process([
            'idempotency_key' => 'coa-pl-1',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $this->warehouse->id, $cashier->id);

        $pl = app(LedgerReportService::class)->profitAndLoss(now()->startOfDay(), now()->endOfDay(), $this->warehouse->id);

        $this->assertEquals(20000, $pl['revenue']);
        $this->assertEquals(8000, $pl['cogs']);
        $this->assertEquals(12000, $pl['gross_profit']);
        $this->assertEquals(12000, $pl['net_profit']);
    }
}
