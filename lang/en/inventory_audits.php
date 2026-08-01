<?php

return [
    'nav_label' => 'Stock Opname',
    'singular' => 'Stock Opname',
    'plural' => 'Stock Opname',
    'submit' => 'Submit Opname',

    'fields' => [
        'product' => 'Product',
        'warehouse' => 'Warehouse',
        'actual_qty' => 'Physical Count',
        'notes' => 'Justification (min 10 characters)',
        'expected_qty' => 'Expected',
        'actual_qty_short' => 'Physical',
        'variance' => 'Variance',
        'status' => 'Status',
        'status_pending' => 'Pending',
        'status_verified' => 'Verified',
        'status_rejected' => 'Rejected',
        'submitted_by' => 'Submitted By',
        'verified_by' => 'Verified By',
        'created_at' => 'Created At',
    ],

    'verify' => [
        'action' => 'Verify',
        'notification' => 'Opname verified, inventory updated',
    ],
];
