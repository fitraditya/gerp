<?php

namespace Tests\Feature\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CheckoutService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\LedgerService;
use App\Services\RemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * RFC §2 Ledger Mechanics: every posting is a balanced two-account movement.
 * Global invariant — SUM(debit) == SUM(credit) — must hold after any sequence
 * of sales/expenses/remittances, individually and cumulatively.
 */
class LedgerInvariantTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    private function assertLedgerBalanced(): void
    {
        $sum = Ledger::withoutGlobalScope(\App\Scopes\WarehouseScope::class)
            ->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();

        $this->assertEquals((float) $sum->d, (float) $sum->c, 'Ledger debit/credit totals diverged.');
    }

    public function test_ledger_stays_balanced_across_sale_expense_and_remittance(): void
    {
        $warehouse = Warehouse::factory()->create();
        $branch = Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $drawer = CashAccount::factory()->create([
            'code' => LedgerService::drawerCode($warehouse->code),
            'branch_id' => $branch->id,
            'balance' => 0,
        ]);
        $product = Product::factory()->create(['price' => 20000]);
        $admin = $this->makeUser('Admin');
        $cashier = $this->makeUser('Staff', $warehouse->id);

        app(InventoryService::class)->receiveStock($product, $warehouse->id, 10, $admin->id);

        app(CheckoutService::class)->process([
            'idempotency_key' => 'ledger-sale-1',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $warehouse->id, $cashier->id);

        $this->assertLedgerBalanced();

        app(ExpenseService::class)->recordExpense(
            $warehouse->id, $drawer->id, 'Pengembangan', 'Beli Hanger', 5000, 'DEV', 'cash', null, $admin->id
        );

        $this->assertLedgerBalanced();

        $central = Warehouse::factory()->central()->create(['code' => 'CENTRAL-'.uniqid()]);
        $remittance = app(RemittanceService::class)->submit($warehouse->id, $central->id, $drawer->id, 10000, $admin->id);

        $this->assertLedgerBalanced();

        $treasury = CashAccount::factory()->create(['balance' => 0]);
        app(RemittanceService::class)->verify($remittance, $treasury->id, $admin->id);

        $this->assertLedgerBalanced();
    }

    public function test_every_ledger_row_has_a_counterpart_sharing_transaction_id(): void
    {
        $warehouse = Warehouse::factory()->create();
        Branch::factory()->create(['warehouse_id' => $warehouse->id]);
        $drawer = CashAccount::factory()->create(['code' => LedgerService::drawerCode($warehouse->code), 'balance' => 0]);
        $product = Product::factory()->create(['price' => 15000]);
        $admin = $this->makeUser('Admin');

        app(InventoryService::class)->receiveStock($product, $warehouse->id, 5, $admin->id);
        app(CheckoutService::class)->process([
            'idempotency_key' => 'ledger-pair-1',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $warehouse->id, $admin->id);

        $rows = Ledger::withoutGlobalScope(\App\Scopes\WarehouseScope::class)->get()->groupBy('transaction_id');

        foreach ($rows as $transactionId => $pair) {
            $this->assertCount(2, $pair, "transaction_id {$transactionId} should have exactly one debit + one credit row.");
            $this->assertEqualsWithDelta(0.0, $pair->sum('debit') - $pair->sum('credit'), 0.001);
        }
    }
}
