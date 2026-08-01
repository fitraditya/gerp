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
        $totalDiskon = (float) (clone $orderBase)->sum('discount_amount');
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

        $totalOmzetNet = $totalSalesGross - $totalDiskon - $totalReturns;
        $totalCogs -= $totalCogsReversal;
        $totalGrossProfit = $totalGrossProfitBeforeReturns - ($totalReturns - $totalCogsReversal);
        $grossMarginPct = $totalOmzetNet > 0 ? ($totalGrossProfit / $totalOmzetNet) * 100 : 0.0;

        $expenseBase = fn (?string $fundPool = null) => Expense::withoutGlobalScope(WarehouseScope::class)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($fundPool, fn ($q) => $q->where('fund_pool', $fundPool));

        $biayaPengembangan = (float) $expenseBase('DEV')->sum('amount');
        $operasionalGerai = (float) $expenseBase('OPS')->sum('amount');
        $biayaSdm = (float) $expenseBase('HR')->sum('amount');
        $jumlahSdm = $expenseBase('HR')->whereNotNull('payee_name')->pluck('payee_name')->unique()->count();

        $branchQuery = Branch::withoutGlobalScope(WarehouseScope::class)->when($branchId, fn ($q) => $q->where('id', $branchId));
        $totalGerai = (clone $branchQuery)->count();
        $geraiAktif = (clone $branchQuery)->where('is_active', true)->count();

        $cashQuery = CashAccount::cash()
            ->when($branchId, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));
        $saldoKas = (float) (clone $cashQuery)->sum('balance');
        $ringkasanKas = (clone $cashQuery)->orderBy('id')->get(['id', 'code', 'name', 'holder_name', 'balance']);

        return [
            'stock_awal_qty' => $stock['qty_awal'],
            'stock_awal_value' => $stock['value_awal'],
            'stock_akhir_qty' => $stock['qty_akhir'],
            'stock_akhir_value' => $stock['value_akhir'],
            'stock_awal_value_cost' => $stock['value_awal_cost'],
            'stock_akhir_value_cost' => $stock['value_akhir_cost'],
            'total_sales_gross' => $totalSalesGross,
            'total_diskon' => $totalDiskon,
            'total_returns' => $totalReturns,
            'total_omzet_net' => $totalOmzetNet,
            'total_cogs' => $totalCogs,
            'total_gross_profit' => $totalGrossProfit,
            'gross_margin_pct' => $grossMarginPct,
            'biaya_pengembangan' => $biayaPengembangan,
            'operasional_gerai' => $operasionalGerai,
            'biaya_sdm' => $biayaSdm,
            'jumlah_sdm' => $jumlahSdm,
            'total_gerai' => $totalGerai,
            'gerai_aktif' => $geraiAktif,
            'saldo_kas' => $saldoKas,
            'ringkasan_kas' => $ringkasanKas,
        ];
    }
}
