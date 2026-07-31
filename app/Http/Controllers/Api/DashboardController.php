<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request, LedgerService $ledgerService)
    {
        $user = $request->user();
        $warehouseId = $user->warehouse_id;

        $todaySales = (float) Order::where('warehouse_id', $warehouseId)
            ->whereDate('completed_at', today())
            ->sum('total');

        $pendingNegativeStock = DB::table('inventories')
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '<', 0)
            ->count();

        $warehouse = $user->warehouse;
        $drawerBalance = $warehouse ? $ledgerService->balance(LedgerService::drawerCode($warehouse->code)) : 0;

        return response()->json([
            'branch_id' => $warehouseId,
            'today_sales_total' => $todaySales,
            'cash_drawer_balance' => $drawerBalance,
            'pending_negative_stock_alerts' => $pendingNegativeStock,
        ]);
    }
}
