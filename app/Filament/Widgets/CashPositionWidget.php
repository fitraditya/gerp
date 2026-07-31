<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CashPositionWidget extends Widget
{
    protected string $view = 'filament.widgets.cash-position-widget';

    public static function canView(): bool
    {
        $user = Auth::user();
        return $user && $user->hasAnyRole(['Admin', 'Manager']);
    }

    protected function getViewData(): array
    {
        $user = Auth::user();

        $query = Ledger::where('account_code', 'SALES_REVENUE')
            ->select(DB::raw('COALESCE(SUM(credit - debit), 0) as balance'));
        
        if (!$user->hasRole('Admin') && $user->warehouse_id) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        $salesRevenue = (float) $query->value('balance');

        $accountsQuery = Ledger::select('account_code', DB::raw('COALESCE(SUM(credit - debit),0) as balance'))
            ->groupBy('account_code')
            ->orderByDesc('balance')
            ->limit(5);
        
        if (!$user->hasRole('Admin') && $user->warehouse_id) {
            $accountsQuery->where('warehouse_id', $user->warehouse_id);
        }

        $cashAccounts = $accountsQuery->get();

        return compact('salesRevenue', 'cashAccounts');
    }
}
