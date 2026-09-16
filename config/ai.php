<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kredensial Claude
    |--------------------------------------------------------------------------
    |
    | Cara yang dianjurkan: super administrator memasang kuncinya di menu
    | Administrasi Sistem → Pengaturan Sistem (tersimpan terenkripsi); kunci
    | itu mengalahkan ANTHROPIC_API_KEY di sini. Bila keduanya kosong, seluruh
    | fitur AI menonaktifkan dirinya sendiri dan dashboard tetap berjalan
    | dengan wawasan yang dihitung dari data aplikasi.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),

    /*
    |--------------------------------------------------------------------------
    | Perilaku
    |--------------------------------------------------------------------------
    */

    // Wawasan dashboard di-cache agar tidak memanggil API tiap kali dibuka.
    'cache_menit' => env('AI_CACHE_MENIT', 60),

    // Batas token jawaban; sengaja moderat karena keluarannya ringkas.
    'max_tokens' => env('AI_MAX_TOKENS', 4096),

    // Kedalaman penalaran: low | medium | high | xhigh | max.
    'effort' => env('AI_EFFORT', 'medium'),

];
