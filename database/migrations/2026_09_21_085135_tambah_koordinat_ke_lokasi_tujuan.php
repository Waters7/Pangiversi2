<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koordinat kota untuk peta perjalanan dinas. Boleh kosong: kota yang
     * namanya dikenal daftar bawaan (KoordinatKota) tetap terpetakan tanpa
     * diisi; kolom ini untuk menimpa atau menambah kota yang tidak dikenal.
     */
    public function up(): void
    {
        Schema::table('lokasi_tujuan', function (Blueprint $table) {
            $table->decimal('lintang', 9, 6)->nullable()->after('jenis');
            $table->decimal('bujur', 9, 6)->nullable()->after('lintang');
        });
    }

    public function down(): void
    {
        Schema::table('lokasi_tujuan', function (Blueprint $table) {
            $table->dropColumn(['lintang', 'bujur']);
        });
    }
};
