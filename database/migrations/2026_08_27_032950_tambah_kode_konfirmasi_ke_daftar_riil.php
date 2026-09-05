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
            // Kode publik pada QR tanda tangan pelaksana, sebagai bukti bahwa
            // nominalnya benar-benar disetujui oleh yang bersangkutan.
            $table->string('kode_konfirmasi', 32)->nullable()->unique()->after('disetujui_pegawai_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->dropColumn('kode_konfirmasi');
        });
    }
};
