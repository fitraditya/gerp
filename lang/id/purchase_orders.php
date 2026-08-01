<?php

return [
    'nav_label' => 'Pesanan Pembelian',
    'singular' => 'Pesanan Pembelian',
    'plural' => 'Pesanan Pembelian',

    'fields' => [
        'po_number' => 'No. PO',
        'supplier' => 'Pemasok',
        'warehouse' => 'Gudang',
        'status' => 'Status',
        'status_ordered' => 'Dipesan',
        'status_partially_received' => 'Diterima Sebagian',
        'status_received' => 'Diterima',
        'status_cancelled' => 'Dibatalkan',
        'total' => 'Nilai Pesanan',
        'received_total' => 'Nilai Diterima',
        'balance_due' => 'Sisa Tagihan',
        'ordered_at' => 'Dipesan Pada',
        'received_at' => 'Diterima Pada',
        'product' => 'Produk',
        'quantity' => 'Jumlah',
        'unit_cost' => 'Harga Satuan',
        'notes' => 'Catatan',
    ],

    'create' => [
        'action' => 'Pesanan Pembelian Baru',
        'notification' => 'Pesanan pembelian dibuat',
    ],

    'receive' => [
        'action' => 'Terima',
        'notification' => 'Stok diterima untuk pesanan pembelian ini',
    ],

    'record_payment' => [
        'action' => 'Catat Pembayaran',
        'cash_account' => 'Dibayar Dari',
        'amount' => 'Jumlah',
        'notification' => 'Pembayaran dicatat',
    ],

    'cancel' => [
        'action' => 'Batalkan',
        'notification' => 'Pesanan pembelian dibatalkan',
    ],
];
