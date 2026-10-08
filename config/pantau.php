<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pemantauan Server
    |--------------------------------------------------------------------------
    |
    | Menu Administrasi Sistem → Pemantauan Server mengukur ukuran aplikasi
    | dan memeriksa kesehatan peladen tiap menit, lalu mengirim peringatan
    | WhatsApp bila aplikasi tidak dapat diakses.
    |
    | Status pemeriksaan dan cermin pengaturannya disimpan sebagai berkas di
    | 'folder', bukan di basis data: peringatan justru paling dibutuhkan
    | ketika basis datanya yang tidak dapat dihubungi.
    |
    */

    // Folder aplikasi yang diukur.
    'akar' => base_path(),

    // Tempat status, riwayat peringatan, dan cermin pengaturan.
    'folder' => storage_path('app/pantau'),

    // Pemeriksaan gagal berturut-turut sebelum peringatan dikirim — satu
    // kegagalan sesaat tidak dianggap gangguan.
    'ambang_gagal' => (int) env('PANTAU_AMBANG_GAGAL', 3),

    // Batas tunggu tiap pemeriksaan jaringan, detik.
    'tenggat_detik' => (int) env('PANTAU_TENGGAT_DETIK', 10),

];
