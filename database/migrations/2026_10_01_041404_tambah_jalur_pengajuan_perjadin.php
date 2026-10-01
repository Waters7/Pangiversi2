<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalur pengajuan perjadin ditanyakan sebelum formulir dibuka: dalam
     * kota, luar kota, atau supervisi kerja praktek / magang.
     *
     * Kategori dan jenis kegiatan supervisi sengaja tidak disisipkan di sini
     * — master data ditambahkan lewat seeder, dan aplikasi menyiapkannya
     * sendiri saat jalur supervisi pertama kali dibuka.
     */
    public function up(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->string('jenis_perjadin', 20)->nullable()->after('jenis_pengajuan');
        });
    }

    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->dropColumn('jenis_perjadin');
        });
    }
};
