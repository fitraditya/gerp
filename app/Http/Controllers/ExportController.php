<?php

namespace App\Http\Controllers;

use App\Filament\Pages\FinancialReports;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Services\CsvExportService;
use App\Services\LedgerReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain CSV downloads for the data an accountant/manager actually needs offline —
 * Filament's UI (FinancialReports/OrderResource/InventoryResource) links here rather
 * than embedding Filament's native async export machinery (see CsvExportService).
 * Authorization mirrors each underlying resource/page exactly — no new permission
 * concept introduced.
 */
class ExportController extends Controller
{
    public function trialBalance(): StreamedResponse
    {
        abort_unless(FinancialReports::canAccess(), 403);

        $rows = app(LedgerReportService::class)->trialBalance()
            ->flatMap(fn ($accounts, $type) => $accounts->map(fn ($a) => [$type, $a['code'], $a['name'], $a['balance']]));

        return CsvExportService::download(
            'trial-balance-'.now()->format('Y-m-d').'.csv',
            ['Account Type', 'Code', 'Name', 'Balance'],
            $rows,
        );
    }

    public function profitAndLoss(Request $request): StreamedResponse
    {
        abort_unless(FinancialReports::canAccess(), 403);

        [$periodStart, $periodEnd, $warehouseId] = $this->resolvePeriodAndWarehouse($request);

        $pl = app(LedgerReportService::class)->profitAndLoss($periodStart, $periodEnd, $warehouseId);

        $rows = [
            ['Revenue', $pl['revenue']],
            ['Cost of Goods Sold', $pl['cogs']],
            ['Gross Profit', $pl['gross_profit']],
            ['Operating Expenses', $pl['operating_expenses']],
            ['Net Profit', $pl['net_profit']],
        ];

        return CsvExportService::download(
            'profit-and-loss-'.$periodStart->format('Y-m-d').'-to-'.$periodEnd->format('Y-m-d').'.csv',
            ['Metric', 'Amount'],
            $rows,
        );
    }

    /**
     * cogs_total/gross_profit columns are omitted for non-Admin/Manager, mirroring
     * CheckoutController::serializeOrder() — see RBAC.md's note to replicate that
     * gate on any endpoint that serializes Order data.
     */
    public function orders(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('viewAny', Order::class), 403);

        [$periodStart, $periodEnd, $warehouseId] = $this->resolvePeriodAndWarehouse($request);
        $showCost = $request->user()->hasAnyRole(['Admin', 'Manager']);

        $orders = Order::with(['warehouse', 'cashier'])
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('completed_at')
            ->cursor();

        $headers = ['Order Number', 'Warehouse', 'Cashier', 'Payment Method', 'Subtotal', 'Discount', 'Total'];
        if ($showCost) {
            $headers[] = 'COGS';
            $headers[] = 'Gross Profit';
        }
        $headers[] = 'Negative Stock Flag';
        $headers[] = 'Completed At';

        $rows = $orders->map(function (Order $order) use ($showCost) {
            $row = [
                $order->order_number,
                $order->warehouse?->name,
                $order->cashier?->name,
                $order->payment_method,
                $order->subtotal,
                $order->discount_amount,
                $order->total,
            ];
            if ($showCost) {
                $row[] = $order->cogs_total;
                $row[] = $order->gross_profit;
            }
            $row[] = $order->has_negative_stock_flag ? 'Yes' : 'No';
            $row[] = $order->completed_at?->toDateTimeString();

            return $row;
        });

        return CsvExportService::download(
            'orders-'.$periodStart->format('Y-m-d').'-to-'.$periodEnd->format('Y-m-d').'.csv',
            $headers,
            $rows,
        );
    }

    public function inventory(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('viewAny', Inventory::class), 403);

        $warehouseId = $request->integer('warehouse_id') ?: null;

        $rows = Inventory::with(['product', 'warehouse'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('product_id')
            ->cursor()
            ->map(fn (Inventory $inv) => [
                $inv->product?->sku,
                $inv->product?->name,
                $inv->warehouse?->name,
                $inv->quantity,
                $inv->quantity_reserved,
                $inv->quantity - $inv->quantity_reserved,
            ]);

        return CsvExportService::download(
            'inventory-'.now()->format('Y-m-d').'.csv',
            ['SKU', 'Product', 'Warehouse', 'Quantity', 'Reserved', 'Available'],
            $rows,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int|null}
     */
    private function resolvePeriodAndWarehouse(Request $request): array
    {
        $periodStart = $request->filled('period_start')
            ? Carbon::parse($request->string('period_start'))->startOfDay()
            : now()->startOfMonth();
        $periodEnd = $request->filled('period_end')
            ? Carbon::parse($request->string('period_end'))->endOfDay()
            : now()->endOfDay();

        $warehouseId = null;
        if ($request->filled('branch_id')) {
            $warehouseId = Branch::find($request->integer('branch_id'))?->warehouse_id;
        }

        return [$periodStart, $periodEnd, $warehouseId];
    }
}
