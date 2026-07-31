<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class InventoryHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.inventory-health-widget';

    public static function canView(): bool
    {
        $user = Auth::user();
        return $user && $user->hasAnyRole(['Admin', 'Manager', 'Supervisor']);
    }

    protected function getViewData(): array
    {
        $user = Auth::user();
        $lowStockThreshold = 5;

        $query = Product::query();
        if (!$user->hasRole('Admin') && $user->warehouse_id) {
            $query->whereHas('inventories', fn ($q) => $q->where('warehouse_id', $user->warehouse_id));
        }

        $totalProducts = $query->count();

        $inventoryQuery = Inventory::whereRaw('quantity - quantity_reserved <= ?', [$lowStockThreshold]);
        if (!$user->hasRole('Admin') && $user->warehouse_id) {
            $inventoryQuery->where('warehouse_id', $user->warehouse_id);
        }
        $lowStock = $inventoryQuery->count();

        $negativeQuery = Inventory::where('quantity', '<', 0);
        if (!$user->hasRole('Admin') && $user->warehouse_id) {
            $negativeQuery->where('warehouse_id', $user->warehouse_id);
        }
        $negativeStock = $negativeQuery->count();

        return compact('totalProducts', 'lowStock', 'negativeStock', 'lowStockThreshold');
    }
}
