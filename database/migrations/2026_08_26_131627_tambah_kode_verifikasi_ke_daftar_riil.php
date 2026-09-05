<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            // Kode publik yang tercetak pada QR sebagai bukti legalitas tanda tangan PPK.
            $table->string('kode_verifikasi', 32)->nullable()->unique()->after('ditandatangani_at');
            $table->timestamp('diajukan_at')->nullable()->after('kode_verifikasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->dropColumn(['kode_verifikasi', 'diajukan_at']);
        });
    }
};
