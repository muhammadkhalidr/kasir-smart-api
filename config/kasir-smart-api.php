<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Key Autentikasi Internal (Untuk akses endpoint)
    |--------------------------------------------------------------------------
    | API Key yang harus disertakan client pada header X-API-Key.
    | Setel via .env: EXTERNAL_API_KEY=xxxxxxxx
    */
    'api_key' => env('EXTERNAL_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | DEEPSEEK API Key (NLP Router)
    |--------------------------------------------------------------------------
    | Key untuk mengekstrak intent dan dimensi ukuran otomatis via DeepSeek LLM.
    | Setel via .env: DEEPSEEK_API_KEY=sk-xxxxxx
    */
    'deepseek_api_key' => env('DEEPSEEK_API_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Maksimum request per menit per API Key.
    | Setel via .env: API_RATE_LIMIT=60
    */
    'rate_limit' => (int) env('API_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix & Middleware
    |--------------------------------------------------------------------------
    | Awalan URL untuk semua endpoint API package ini.
    | Default: /api/v1/... (sudah termasuk prefix 'api' dari Laravel)
    */
    'route_prefix' => 'api/v1',

    /*
    |--------------------------------------------------------------------------
    | Model Class Mapping
    |--------------------------------------------------------------------------
    | Package ini menggunakan model dari host application.
    | Pastikan model di project Anda memiliki kolom yang sesuai.
    |
    | Model Produk     : harus memiliki kolom id, judul, harga_jual, harga_beli,
    |                    harga_grosir, barcode, ukuran, satuan, status, id_kategori
    | Model Kategori   : harus memiliki kolom id, kategori, status
    | Model Pelanggan  : harus memiliki kolom id, nama, nohp, email, alamat, kota, jk
    */
    'models' => [
        'produk'     => \App\Models\Produk::class,
        'kategori'   => \App\Models\KategoriBahan::class,
        'pelanggan'  => \App\Models\Pelanggan::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Channel log untuk mencatat setiap request API masuk.
    | Pastikan channel ini terdaftar di config/logging.php
    */
    'log_channel' => env('API_LOG_CHANNEL', 'api'),

    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    | Daftar origin yang diizinkan mengakses API ini.
    | '*' berarti semua origin diizinkan.
    */
    'allowed_origins' => ['*'],
];
