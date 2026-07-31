<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Ledger;
use App\Scopes\WarehouseScope;

/**
 * Real Chart-of-Accounts reporting on top of `cash_accounts` (see CashAccount::
 * ACCOUNT_TYPES). `cash_accounts.balance` is already a live running trial balance —
 * LedgerService::post() maintains it on every posting — so trialBalance() just
 * classifies/subtotals it. profitAndLoss() is period-scoped from the Ledger log
 * instead, since `.balance` is an all-time total, not bounded to a date range.
 */
class LedgerReportService
{
    /**
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection> accounts grouped by account_type
     */
    public function trialBalance(): \Illuminate\Support\Collection
    {
        return CashAccount::query()
            ->orderBy('account_type')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type', 'balance'])
            ->groupBy('account_type');
    }

    /**
     * Revenue and expense (fund pools + COGS_EXPENSE) recognized within the period.
     * Per LedgerService::post()'s "from loses / to gains" mechanics: a sale posts
     * from:SALES_REVENUE (so revenue lands in the `debit` column), an expense/COGS
     * posts to:<expense account> (so expense lands in the `credit` column) — this
     * is this schema's internal bookkeeping direction, not GAAP normal balance.
     */
    public function profitAndLoss(\DateTimeInterface $periodStart, \DateTimeInterface $periodEnd, ?int $warehouseId = null): array
    {
        $revenueCodes = CashAccount::where('account_type', 'revenue')->pluck('code');
        $expenseCodes = CashAccount::where('account_type', 'expense')->pluck('code');

        $ledgerBase = Ledger::withoutGlobalScope(WarehouseScope::class)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $revenue = (float) (clone $ledgerBase)->whereIn('account_code', $revenueCodes)->sum('debit');
        $cogs = (float) (clone $ledgerBase)->where('account_code', 'COGS_EXPENSE')->sum('credit');
        $totalExpenses = (float) (clone $ledgerBase)->whereIn('account_code', $expenseCodes)->sum('credit');
        $operatingExpenses = $totalExpenses - $cogs;

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $revenue - $cogs,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $revenue - $totalExpenses,
        ];
    }
}
