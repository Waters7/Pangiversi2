<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Validasi transport lokal oleh tim keuangan.
 *
 * Nominal pada daftar riil datang dari nota yang diunggah pelaksana, jadi
 * harus diperiksa lebih dulu — sama seperti baris rincian biaya. Tanpa
 * penjagaan ini, angka yang belum tentu benar dapat berjalan sampai ke
 * tanda tangan PPK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->timestamp('divalidasi_at')->nullable()->after('diajukan_at');
            $table->foreignId('id_validator')->nullable()->after('divalidasi_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_validator');
            $table->dropColumn('divalidasi_at');
        });
    }
};
