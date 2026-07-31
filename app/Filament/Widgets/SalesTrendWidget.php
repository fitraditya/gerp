<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SalesTrendWidget extends Widget
{
    protected string $view = 'filament.widgets.sales-trend-widget';

    public static function canView(): bool
    {
        $user = Auth::user();
        return $user && $user->hasAnyRole(['Admin', 'Manager', 'Supervisor']);
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $days = 7;
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $query = DB::table('orders')
            ->select(DB::raw('DATE(completed_at) as day'), DB::raw('COALESCE(SUM(total),0) as total'))
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $start);

        if (!$user->hasRole('Admin') && $user->warehouse_id) {
            $query->where('warehouse_id', $user->warehouse_id);
        }

        $rows = $query->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $series[] = [
                'day' => $d,
                'total' => (float) ($rows[$d]->total ?? 0),
            ];
        }

        return compact('series');
    }
}
