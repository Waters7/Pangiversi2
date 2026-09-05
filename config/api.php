<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token API
    |--------------------------------------------------------------------------
    |
    | Kunci yang harus dibawa aplikasi lain untuk membaca data PANGI. Dibiarkan
    | kosong secara bawaan supaya API tertutup selama tokennya belum dipasang
    | di berkas .env — data eksekutif memuat realisasi anggaran dan sebaran
    | pegawai, jadi tidak boleh terbuka hanya karena pengaturannya terlewat.
    |
    | Buat token acak dengan:  php artisan pangi:token-api
    |
    */

    'token' => env('PANGI_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Asal Permintaan yang Diizinkan
    |--------------------------------------------------------------------------
    |
    | Daftar origin aplikasi lain yang boleh memanggil API ini langsung dari
    | peramban. Dipisahkan koma pada .env, misalnya:
    |
    |     PANGI_API_ORIGINS="http://172.16.70.249:8000,http://localhost:5173"
    |
    | Dibiarkan kosong berarti tidak ada peramban yang diizinkan; pemanggilan
    | dari sisi server tidak terpengaruh karena CORS hanya berlaku di peramban.
    |
    */

    'origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('PANGI_API_ORIGINS', '')))
    )),

];
