<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sejak tanda tangan PPK langsung mengirim daftar nominatif ke tim
 * keuangan, daftar yang sudah ditandatangani tetapi tertahan belum dikirim
 * dianggap terkirim pada saat ditandatangani.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('daftar_nominatif')
            ->whereNotNull('ditandatangani_at')
            ->whereNull('dikirim_at')
            ->update(['dikirim_at' => DB::raw('ditandatangani_at')]);
    }

    /**
     * Tidak dikembalikan: setelah dijalankan tidak lagi terbedakan daftar
     * yang dikirim PPK sendiri dari yang terkirim lewat migrasi ini.
     */
    public function down(): void {}
};
