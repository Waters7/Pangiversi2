<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lintas Asal (CORS)
    |--------------------------------------------------------------------------
    |
    | Hanya jalur API yang dibuka untuk aplikasi lain; halaman web PANGI tidak
    | ikut, supaya sesi penggunanya tidak dapat dipanggil dari situs luar.
    |
    | Daftar origin-nya diatur lewat PANGI_API_ORIGINS pada .env. Sengaja tidak
    | memakai "*": dengan wildcard, situs mana pun dapat menyuruh peramban
    | pegawai memanggil API ini.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    // Dibaca langsung dari env, bukan lewat config('api.origins'): saat
    // konfigurasi di-cache, urutan pemuatan berkas menentukan apakah api.php
    // sudah tersedia di sini. Menyalin satu baris ini lebih murah daripada
    // CORS yang diam-diam kosong di server produksi.
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('PANGI_API_ORIGINS', '')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'X-Api-Token', 'Accept', 'Content-Type'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Token dibawa pada header, bukan cookie — jadi kredensial tidak perlu
    // ikut dikirim, dan API ini tidak bisa dipakai membonceng sesi pengguna.
    'supports_credentials' => false,

];
