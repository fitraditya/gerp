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
        'stock_opening_qty' => 'Opening Stock',
        'stock_opening_qty_sub' => 'Items',
        'stock_opening_value' => 'Opening Stock Value',
        'stock_opening_value_sub' => 'Incoming Goods',
        'stock_closing_qty' => 'Closing Stock',
        'stock_closing_qty_sub' => 'Items',
        'stock_closing_value' => 'Closing Stock Value',
        'stock_closing_value_sub' => 'Remaining Goods',

        'total_sales' => 'Total Sales',
        'total_sales_sub' => '(Gross)',
        'total_discount' => 'Total Discount',
        'total_discount_sub' => 'Discount / Partner Promo',
        'total_returns' => 'Total Returns',
        'total_returns_sub' => 'Refunded to Customers',
        'total_net_revenue' => 'Net Revenue',
        'total_net_revenue_sub' => 'Net Sales',
        'development_cost' => 'Development Cost',
        'development_cost_sub' => 'Asset Value',

        'total_branches' => 'Active Branches',
        'total_branches_sub' => 'Active out of :total registered',
        'branch_operations' => 'Branch Operations',
        'staff_cost' => 'Staff Cost',
        'staff_cost_sub' => ':count Staff',
        'cash_balance' => 'Cash Balance',

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
