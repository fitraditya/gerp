<?php

return [
    'nav_label' => 'Transaksi',
    'singular' => 'Log Transaksi',
    'plural' => 'Transaksi',

    'fields' => [
        'order_number' => 'Nomor Transaksi',
        'warehouse' => 'Gudang',
        'cashier' => 'Kasir',
        'discount' => 'Diskon',
        'subtotal' => 'Subtotal',
        'discount_amount' => 'Diskon',
        'total' => 'Total',
        'payment_method' => 'Metode Pembayaran',
        'payment_method_cash' => 'Tunai',
        'payment_method_qris' => 'QRIS',
        'negative_stock_flag' => 'Tanda Stok Minus',
        'negative_stock_short' => 'Stok Minus',
        'completed_at' => 'Selesai Pada',
        'item_product' => 'Produk',
        'item_quantity' => 'Jumlah',
        'item_unit_price' => 'Harga Satuan',
        'item_subtotal' => 'Subtotal',
    ],

    'export_csv' => 'Ekspor CSV',

    'return' => [
        'action' => 'Proses Retur',
        'reason' => 'Alasan',
        'refund_method' => 'Refund Via',
        'refund_method_help' => 'Default ke metode pembayaran asli transaksi jika dikosongkan.',
        'item_product' => 'Produk',
        'item_quantity' => 'Jumlah',
        'processed_notification' => 'Retur berhasil diproses',
    ],
];
