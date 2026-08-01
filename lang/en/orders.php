<?php

return [
    'nav_label' => 'Orders',
    'singular' => 'Order Log',
    'plural' => 'Orders',

    'fields' => [
        'order_number' => 'Order Number',
        'warehouse' => 'Warehouse',
        'cashier' => 'Cashier',
        'discount' => 'Discount',
        'subtotal' => 'Subtotal',
        'discount_amount' => 'Discount',
        'total' => 'Total',
        'payment_method' => 'Payment Method',
        'payment_method_cash' => 'Cash',
        'payment_method_qris' => 'QRIS',
        'negative_stock_flag' => 'Negative Stock Flag',
        'negative_stock_short' => 'Neg. Stock',
        'completed_at' => 'Completed At',
        'item_product' => 'Product',
        'item_quantity' => 'Quantity',
        'item_unit_price' => 'Unit Price',
        'item_subtotal' => 'Subtotal',
    ],

    'export_csv' => 'Export CSV',

    'return' => [
        'action' => 'Process Return',
        'reason' => 'Reason',
        'refund_method' => 'Refund Via',
        'refund_method_help' => 'Defaults to the order\'s original payment method if left blank.',
        'item_product' => 'Product',
        'item_quantity' => 'Quantity',
        'processed_notification' => 'Return processed',
    ],
];
