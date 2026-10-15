<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas laporan perjalanan dinas yang diunggah pelaksana — opsional,
 * melengkapi isian laporan di aplikasi (misalnya laporan lengkap berfoto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->string('berkas_laporan')->nullable()->after('kesimpulan');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->dropColumn('berkas_laporan');
        });
    }
};
