<?php

return [
    'nav_label' => 'Remittances',
    'singular' => 'Cash Remittance (Setoran Kas)',
    'plural' => 'Remittances',
    'submit' => 'Submit Remittance',

    'fields' => [
        'from_warehouse' => 'Branch Warehouse',
        'source_cash_account' => 'Cash Source',
        'amount' => 'Amount',
        'remittance_number' => 'Remittance No.',
        'from' => 'From',
        'to' => 'To',
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
        'deposit_into' => 'Deposit Into',
        'notification' => 'Remittance verified, funds moved to treasury',
    ],
];
