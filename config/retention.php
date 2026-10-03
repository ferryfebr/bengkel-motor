<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pagar Retensi Data (RESIKO_HOSTING.md)
    |--------------------------------------------------------------------------
    | Batas-batas yang dipakai command retensi & panel sistem agar disk hosting
    | tidak penuh. Ubah lewat .env bila perlu.
    */

    // Jumlah transaksi final maksimal yang disimpan di database.
    'transactions' => (int) env('RETENTION_TRANSACTIONS', 8000),

    // Jumlah maksimal baris activity_logs yang disimpan.
    'activity_max' => (int) env('RETENTION_ACTIVITY_MAX', 3000),

    // Umur maksimal activity_logs (bulan).
    'activity_months' => (int) env('RETENTION_ACTIVITY_MONTHS', 3),

    // Kuota disk akun hosting (MB) untuk indikator Panel Sistem.
    'disk_quota_mb' => (int) env('DISK_QUOTA_MB', 2048),

    // Jumlah maksimal pesanan pembelian (PO) yang disimpan.
    'purchase_orders' => (int) env('RETENTION_PURCHASE_ORDERS', 2000),
];
