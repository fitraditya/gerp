<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Scopes\WarehouseScope;

class DashboardService
{
    public function __construct(private InventoryReportService $inventoryReportService)
    {
    }

    public function summary(\DateTimeInterface $periodStart, \DateTimeInterface $periodEnd, ?int $branchId = null): array
    {
        $warehouseId = $branchId
            ? Branch::withoutGlobalScope(WarehouseScope::class)->findOrFail($branchId)->warehouse_id
            : null;

        $stock = $this->inventoryReportService->stockSummary($warehouseId, $periodStart);

        $orderBase = Order::withoutGlobalScope(WarehouseScope::class)
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $totalSalesGross = (float) (clone $orderBase)->sum('subtotal');
        $totalDiscount = (float) (clone $orderBase)->sum('discount_amount');
        $totalCogs = (float) (clone $orderBase)->sum('cogs_total');
        $totalGrossProfitBeforeReturns = (float) (clone $orderBase)->sum('gross_profit');

        // Returns (Phase 4): a return processed within the period reverses part of a
        // sale — deduct it from net sales/COGS/gross-profit here rather than mutating
        // the original completed Order (which stays an immutable sales record).
        $returnBase = SalesReturn::withoutGlobalScope(WarehouseScope::class)
            ->whereBetween('processed_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $totalReturns = (float) (clone $returnBase)->sum('refund_amount');
        $totalCogsReversal = (float) (clone $returnBase)->sum('cogs_reversal');

        $totalNetRevenue = $totalSalesGross - $totalDiscount - $totalReturns;
        $totalCogs -= $totalCogsReversal;
        $totalGrossProfit = $totalGrossProfitBeforeReturns - ($totalReturns - $totalCogsReversal);
        $grossMarginPct = $totalNetRevenue > 0 ? ($totalGrossProfit / $totalNetRevenue) * 100 : 0.0;

        $expenseBase = fn (?string $fundPool = null) => Expense::withoutGlobalScope(WarehouseScope::class)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($fundPool, fn ($q) => $q->where('fund_pool', $fundPool));

        $developmentCost = (float) $expenseBase('DEV')->sum('amount');
        $branchOperations = (float) $expenseBase('OPS')->sum('amount');
        $staffCost = (float) $expenseBase('HR')->sum('amount');
        $staffCount = $expenseBase('HR')->whereNotNull('payee_name')->pluck('payee_name')->unique()->count();

        $branchQuery = Branch::withoutGlobalScope(WarehouseScope::class)->when($branchId, fn ($q) => $q->where('id', $branchId));
        $totalBranches = (clone $branchQuery)->count();
        $activeBranches = (clone $branchQuery)->where('is_active', true)->count();

        $cashQuery = CashAccount::cash()
            ->when($branchId, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));
        $cashBalance = (float) (clone $cashQuery)->sum('balance');
        $cashSummary = (clone $cashQuery)->orderBy('id')->get(['id', 'code', 'name', 'holder_name', 'balance']);

        return [
            'stock_opening_qty' => $stock['qty_opening'],
            'stock_opening_value' => $stock['value_opening'],
            'stock_closing_qty' => $stock['qty_closing'],
            'stock_closing_value' => $stock['value_closing'],
            'stock_opening_value_cost' => $stock['value_opening_cost'],
            'stock_closing_value_cost' => $stock['value_closing_cost'],
            'total_sales_gross' => $totalSalesGross,
            'total_discount' => $totalDiscount,
            'total_returns' => $totalReturns,
            'total_net_revenue' => $totalNetRevenue,
            'total_cogs' => $totalCogs,
            'total_gross_profit' => $totalGrossProfit,
            'gross_margin_pct' => $grossMarginPct,
            'development_cost' => $developmentCost,
            'branch_operations' => $branchOperations,
            'staff_cost' => $staffCost,
            'staff_count' => $staffCount,
            'total_branches' => $totalBranches,
            'active_branches' => $activeBranches,
            'cash_balance' => $cashBalance,
            'cash_summary' => $cashSummary,
        ];
    }
}
