<?php

return [
    'nav_label' => 'Inventory',
    'singular' => 'Inventory',
    'plural' => 'Inventory',

    'fields' => [
        'sku' => 'SKU',
        'product' => 'Product',
        'warehouse' => 'Warehouse',
        'quantity' => 'Quantity',
        'reserved' => 'Reserved',
        'available' => 'Available',
    ],

    'export_csv' => 'Export CSV',

    'receive_stock' => [
        'action' => 'Receive Stock',
        'quantity' => 'Quantity',
        'funding_source' => 'Paid From',
        'funding_source_help' => 'Optional. Only needed if this stock was purchased (not donated) and you want it posted as a real inventory asset against the account that paid for it.',
        'notification' => 'Stock received',
    ],

    'transfer' => [
        'action' => 'Transfer',
        'destination_warehouse' => 'Destination Warehouse',
        'quantity' => 'Quantity',
        'notification' => 'Transfer completed',
    ],
];
