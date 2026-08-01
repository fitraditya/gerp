<?php

return [
    'nav_label' => 'Stok',
    'singular' => 'Stok',
    'plural' => 'Stok',

    'fields' => [
        'sku' => 'SKU',
        'product' => 'Produk',
        'warehouse' => 'Gudang',
        'quantity' => 'Jumlah',
        'reserved' => 'Dipesan',
        'available' => 'Tersedia',
    ],

    'export_csv' => 'Ekspor CSV',

    'receive_stock' => [
        'action' => 'Terima Stok',
        'quantity' => 'Jumlah',
        'funding_source' => 'Dibayar Dari',
        'funding_source_help' => 'Opsional. Hanya diisi jika stok ini dibeli (bukan donasi) dan ingin dicatat sebagai aset persediaan terhadap akun yang membayarnya.',
        'notification' => 'Stok diterima',
    ],

    'transfer' => [
        'action' => 'Transfer',
        'destination_warehouse' => 'Gudang Tujuan',
        'quantity' => 'Jumlah',
        'notification' => 'Transfer selesai',
    ],
];
