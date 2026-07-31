<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    /**
     * Story 4: source cash account -> named fund pool. Pool balance is a running spend
     * total for reporting, not spendable cash (see RFC §2 Ledger Mechanics).
     *
     * $sourceCashAccountId is explicit (not derived from warehouse) because cash is
     * held per-person in this org, not one drawer per branch — a Manager picks which
     * holder's cash the expense actually came out of.
     */
    public function recordExpense(
        int $warehouseId,
        int $sourceCashAccountId,
        string $category,
        string $description,
        float $amount,
        string $fundPool,
        ?string $paymentMethod,
        ?string $payeeName,
        int $actorId,
        ?\DateTimeInterface $occurredAt = null
    ): Expense {
        return DB::transaction(function () use ($warehouseId, $sourceCashAccountId, $category, $description, $amount, $fundPool, $paymentMethod, $payeeName, $actorId, $occurredAt) {
            $source = CashAccount::lockForUpdate()->findOrFail($sourceCashAccountId);

            if ($amount > (float) $source->balance) {
                throw new \RuntimeException('Insufficient account funds.');
            }

            $expense = Expense::create([
                'reference_number' => 'EXP-'.uniqid(),
                'warehouse_id' => $warehouseId,
                'created_by' => $actorId,
                'category' => $category,
                'description' => $description,
                'payee_name' => $payeeName,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'fund_pool' => $fundPool,
                'status' => 'recorded',
                'created_at' => $occurredAt ?? now(),
            ]);

            $this->ledgerService->post(
                from: $source,
                to: LedgerService::poolCode($fundPool),
                amount: $amount,
                type: 'expense',
                description: "Expense {$expense->reference_number}: {$description}",
                transactionable: $expense,
                warehouseId: $warehouseId,
                actorId: $actorId,
                occurredAt: $occurredAt,
            );

            return $expense;
        });
    }
}
