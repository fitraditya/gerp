<?php

return [
    'nav_label' => 'Purchase Orders',
    'singular' => 'Purchase Order',
    'plural' => 'Purchase Orders',

    'fields' => [
        'po_number' => 'PO Number',
        'supplier' => 'Supplier',
        'warehouse' => 'Warehouse',
        'status' => 'Status',
        'status_ordered' => 'Ordered',
        'status_partially_received' => 'Partially Received',
        'status_received' => 'Received',
        'status_cancelled' => 'Cancelled',
        'total' => 'Ordered Value',
        'received_total' => 'Received Value',
        'balance_due' => 'Balance Due',
        'ordered_at' => 'Ordered At',
        'received_at' => 'Received At',
        'product' => 'Product',
        'quantity' => 'Quantity',
        'unit_cost' => 'Unit Cost',
        'notes' => 'Notes',
    ],

    'create' => [
        'action' => 'New Purchase Order',
        'notification' => 'Purchase order created',
    ],

    'receive' => [
        'action' => 'Receive',
        'notification' => 'Stock received against purchase order',
    ],

    'record_payment' => [
        'action' => 'Record Payment',
        'cash_account' => 'Paid From',
        'amount' => 'Amount',
        'notification' => 'Payment recorded',
    ],

    'cancel' => [
        'action' => 'Cancel',
        'notification' => 'Purchase order cancelled',
    ],
];
