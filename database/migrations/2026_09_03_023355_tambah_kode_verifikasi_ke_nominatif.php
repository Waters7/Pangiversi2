<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode verifikasi tanda tangan PPK pada daftar nominatif.
 *
 * Dicetak sebagai QR menggantikan penanda tanda tangan, sehingga pemeriksa
 * dokumen fisik dapat memastikan keabsahannya lewat halaman verifikasi
 * publik — pola yang sama dengan daftar pengeluaran riil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->string('kode_verifikasi', 32)->nullable()->unique()->after('ditandatangani_at');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->dropColumn('kode_verifikasi');
        });
    }
};
