<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;

class InventoryReportService
{
    /**
     * Stock Akhir is always the live, real-time quantity. Stock Awal is reconstructed
     * by rewinding the movement log: qty_at_period_start = qty_now - SUM(deltas that
     * happened on/after the period start). Value is priced at the product's CURRENT
     * price (no historical price tracking) — matches how the source spreadsheet
     * itself only ever shows one price per tier, not a price-at-the-time.
     *
     * value_* uses retail price (sell-through value); value_*_cost uses cost_price
     * (what the stock on hand is actually worth on the books). Null cost_price reads
     * as 0, same convention as CheckoutService's COGS snapshot.
     *
     * Period-boundary convention: movements timestamped ON $periodStart count as
     * in-period, so Stock Awal excludes them. An opening intake dated exactly at the
     * period start therefore yields Stock Awal = 0 for those items — pick a period
     * that starts strictly AFTER the opening intake date (e.g. intake 20 Jun, report
     * from 21 Jun) to see the intake as Stock Awal.
     *
     * @param  int|null  $warehouseId  null = all warehouses (org-wide)
     */
    public function stockSummary(?int $warehouseId, \DateTimeInterface $periodStart): array
    {
        $currentQuery = Inventory::query();
        if ($warehouseId) {
            $currentQuery->where('warehouse_id', $warehouseId);
        }
        $currentByProduct = $currentQuery
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $movementQuery = InventoryMovement::where('created_at', '>=', $periodStart);
        if ($warehouseId) {
            $movementQuery->where('warehouse_id', $warehouseId);
        }
        $movedSincePeriodStart = $movementQuery
            ->selectRaw('product_id, SUM(quantity_delta) as delta')
            ->groupBy('product_id')
            ->pluck('delta', 'product_id');

        $prices = Product::pluck('price', 'id');
        $costs = Product::pluck('cost_price', 'id');

        $qtyOpening = 0;
        $qtyClosing = 0;
        $valueOpening = 0.0;
        $valueClosing = 0.0;
        $valueOpeningCost = 0.0;
        $valueClosingCost = 0.0;

        foreach ($currentByProduct as $productId => $qtyNow) {
            $delta = (int) ($movedSincePeriodStart[$productId] ?? 0);
            $qtyStart = $qtyNow - $delta;
            $price = (float) ($prices[$productId] ?? 0);
            $cost = (float) ($costs[$productId] ?? 0);

            $qtyOpening += $qtyStart;
            $qtyClosing += $qtyNow;
            $valueOpening += $qtyStart * $price;
            $valueClosing += $qtyNow * $price;
            $valueOpeningCost += $qtyStart * $cost;
            $valueClosingCost += $qtyNow * $cost;
        }

        return [
            'qty_opening' => $qtyOpening,
            'qty_closing' => $qtyClosing,
            'value_opening' => $valueOpening,
            'value_closing' => $valueClosing,
            'value_opening_cost' => $valueOpeningCost,
            'value_closing_cost' => $valueClosingCost,
        ];
    }
}
