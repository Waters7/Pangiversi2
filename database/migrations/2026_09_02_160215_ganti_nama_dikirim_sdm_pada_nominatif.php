<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar nominatif kini dikirim ke tim keuangan, bukan Tim SDM — merekalah
 * yang memprosesnya menjadi pembayaran. Nama kolomnya ikut disesuaikan
 * supaya tidak menyesatkan pembaca berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->renameColumn('dikirim_sdm_at', 'dikirim_at');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->renameColumn('dikirim_at', 'dikirim_sdm_at');
        });
    }
};
