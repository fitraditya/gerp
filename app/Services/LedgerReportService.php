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
     * Revenue and expense (fund pools + COGS_EXPENSE) recognized within the period, net
     * of returns (Phase 4). Per LedgerService::post()'s "from loses / to gains"
     * mechanics: a sale posts from:SALES_REVENUE/to:<expense account> (debit column);
     * SalesReturnService posts the exact reverse (credit column) for both — so net
     * revenue is SUM(debit)-SUM(credit) on revenue-type accounts, and net expense is
     * SUM(credit)-SUM(debit) on expense-type accounts. This is this schema's internal
     * bookkeeping direction, not GAAP normal balance.
     */
    public function profitAndLoss(\DateTimeInterface $periodStart, \DateTimeInterface $periodEnd, ?int $warehouseId = null): array
    {
        $revenueCodes = CashAccount::where('account_type', 'revenue')->pluck('code');
        $expenseCodes = CashAccount::where('account_type', 'expense')->pluck('code');

        $ledgerBase = Ledger::withoutGlobalScope(WarehouseScope::class)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $netFor = fn ($codes, $increaseColumn, $decreaseColumn) => (float) (clone $ledgerBase)
            ->whereIn('account_code', $codes)
            ->selectRaw("COALESCE(SUM({$increaseColumn}),0) - COALESCE(SUM({$decreaseColumn}),0) as net")
            ->value('net');

        $revenue = $netFor($revenueCodes, 'debit', 'credit');
        $cogs = $netFor(['COGS_EXPENSE'], 'credit', 'debit');
        $totalExpenses = $netFor($expenseCodes, 'credit', 'debit');
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
