<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uraian kegiatan dicatat per hari perjalanan, dan hasil yang dicapai dipilih
 * dari master status — bukan lagi diketik bebas.
 *
 * Kolom "dasar" dibiarkan ada untuk laporan lama; isinya kini diturunkan dari
 * nomor surat tugas dan maksud perjalanan, jadi tidak lagi diminta ke
 * pelaksana. Kolom "hasil" pun ditinggalkan, bukan dihapus, supaya laporan
 * yang terlanjur terisi tidak kehilangan riwayatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_kegiatan', function (Blueprint $table): void {
            $table->date('tanggal')->nullable()->after('urutan');
        });

        Schema::table('laporan_perjadin', function (Blueprint $table): void {
            $table->foreignId('id_status_hasil')->nullable()->after('hasil')
                ->constrained('status_hasil')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table): void {
            $table->dropForeign(['id_status_hasil']);
            $table->dropColumn('id_status_hasil');
        });

        Schema::table('laporan_kegiatan', function (Blueprint $table): void {
            $table->dropColumn('tanggal');
        });
    }
};
