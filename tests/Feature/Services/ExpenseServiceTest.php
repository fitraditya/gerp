<?php

namespace Tests\Feature\Services;

use App\Models\CashAccount;
use App\Models\Warehouse;
use App\Services\ExpenseService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsErp;
use Tests\TestCase;

/**
 * PRD Story 4: Expense entry / fund-pool routing.
 */
class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase, SeedsErp;

    private ExpenseService $service;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
        $this->service = app(ExpenseService::class);
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_expense_debits_source_and_credits_fund_pool(): void
    {
        // PRD AC: Rp10.000.000 balance, Rp2.000.000 HR payout -> source drops to Rp8.000.000.
        $source = CashAccount::factory()->create(['balance' => 10000000]);
        $manager = $this->makeUser('Manager');

        $expense = $this->service->recordExpense($this->warehouse->id, $source->id, 'SDM', 'Honor Petugas', 2000000, 'HR', 'cash', 'Fadhil', $manager->id);

        $this->assertEquals('recorded', $expense->status);
        $this->assertEquals(8000000, $source->fresh()->balance);
        $this->assertEquals(2000000, CashAccount::where('code', LedgerService::poolCode('HR'))->value('balance'));
    }

    public function test_expense_rejects_amount_exceeding_source_balance(): void
    {
        // PRD AC: "Insufficient Account Funds" — request cancelled, no partial deduction.
        $source = CashAccount::factory()->create(['balance' => 100000]);
        $manager = $this->makeUser('Manager');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient account funds.');

        $this->service->recordExpense($this->warehouse->id, $source->id, 'Pengembangan', 'Beli Hanger', 500000, 'DEV', 'cash', null, $manager->id);

        $this->assertEquals(100000, $source->fresh()->balance);
    }
}
