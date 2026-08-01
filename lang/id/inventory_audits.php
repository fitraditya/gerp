<?php

return [
    'nav_label' => 'Stock Opname',
    'singular' => 'Stock Opname',
    'plural' => 'Stock Opname',
    'submit' => 'Ajukan Opname',

    'fields' => [
        'product' => 'Produk',
        'warehouse' => 'Gudang',
        'actual_qty' => 'Hasil Hitung Fisik',
        'notes' => 'Keterangan (min 10 karakter)',
        'expected_qty' => 'Sistem',
        'actual_qty_short' => 'Fisik',
        'variance' => 'Selisih',
        'status' => 'Status',
        'status_pending' => 'Menunggu',
        'status_verified' => 'Terverifikasi',
        'status_rejected' => 'Ditolak',
        'submitted_by' => 'Diajukan Oleh',
        'verified_by' => 'Diverifikasi Oleh',
        'created_at' => 'Dibuat Pada',
    ],

    'verify' => [
        'action' => 'Verifikasi',
        'notification' => 'Opname terverifikasi, stok telah diperbarui',
    ],
];
