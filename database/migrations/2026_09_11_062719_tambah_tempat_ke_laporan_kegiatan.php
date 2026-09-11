<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat kegiatan diisi per hari pada laporan perjalanan dinas.
 *
 * Sebelumnya tempatnya diturunkan dari instansi dan lokasi usulan dan hanya
 * tercetak sekali, padahal kegiatan tiap hari bisa berlangsung di tempat
 * yang berbeda — rapat di kantor dinas, lalu kunjungan ke puskesmas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_kegiatan', function (Blueprint $table) {
            $table->string('tempat')->nullable()->after('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_kegiatan', function (Blueprint $table) {
            $table->dropColumn('tempat');
        });
    }
};
