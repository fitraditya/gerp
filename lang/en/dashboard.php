<?php

return [
    'nav_label' => 'Reports',

    'filter' => [
        'period_start' => 'From Date',
        'period_end' => 'To Date',
        'branch' => 'Branch',
        'all_branches' => 'All Branches',
    ],

    'tiles' => [
        'stock_awal_qty' => 'Opening Stock',
        'stock_awal_qty_sub' => 'Items',
        'stock_awal_value' => 'Opening Stock Value',
        'stock_awal_value_sub' => 'Incoming Goods',
        'stock_akhir_qty' => 'Closing Stock',
        'stock_akhir_qty_sub' => 'Items',
        'stock_akhir_value' => 'Closing Stock Value',
        'stock_akhir_value_sub' => 'Remaining Goods',

        'total_sales' => 'Total Sales',
        'total_sales_sub' => '(Gross)',
        'total_diskon' => 'Total Discount',
        'total_diskon_sub' => 'Discount / Partner Promo',
        'total_returns' => 'Total Returns',
        'total_returns_sub' => 'Refunded to Customers',
        'total_omzet' => 'Net Revenue',
        'total_omzet_sub' => 'Net Sales',
        'biaya_pengembangan' => 'Development Cost',
        'biaya_pengembangan_sub' => 'Asset Value',

        'total_gerai' => 'Active Branches',
        'total_gerai_sub' => 'Active out of :total registered',
        'operasional_gerai' => 'Branch Operations',
        'biaya_sdm' => 'Staff Cost',
        'biaya_sdm_sub' => ':count Staff',
        'saldo_kas' => 'Cash Balance',

        'total_cogs' => 'Cost of Goods Sold',
        'total_cogs_sub' => 'COGS (Net Sales Period)',
        'total_gross_profit' => 'Gross Profit',
        'total_gross_profit_sub' => ':margin% Margin',
    ],

    'cash_table' => [
        'title' => 'Cash Summary',
        'account' => 'Cash Account',
        'holder' => 'Holder',
        'balance' => 'Balance',
        'total' => 'Cash Balance',
    ],
];
