<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\Order;
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
        $totalOmzetNet = $totalSalesGross - $totalDiskon;

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
            'total_sales_gross' => $totalSalesGross,
            'total_diskon' => $totalDiskon,
            'total_omzet_net' => $totalOmzetNet,
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
