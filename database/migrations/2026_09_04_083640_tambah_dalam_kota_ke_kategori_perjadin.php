<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perjalanan dalam kota menuntut berkas yang jauh lebih sedikit.
 *
 * Luar kota melibatkan tiket, penginapan, dan kuitansi; dalam kota tidak
 * satu pun dari itu — yang ada hanya surat tugas, SPD, dan transport
 * lokalnya. Sebelumnya perbedaan itu hanya tersirat pada nama kategori,
 * sehingga sistem menagih bill hotel kepada orang yang pulang hari itu juga.
 *
 * Penandanya ditaruh pada kategori, bukan ditebak dari namanya tiap kali:
 * administrator dapat menyesuaikannya lewat Master Data tanpa menyentuh kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kategori_perjadin', function (Blueprint $table): void {
            $table->boolean('dalam_kota')->default(false)->after('kode');
        });

        // Isian awal dibaca dari nama kategorinya, yang selama ini memang
        // sudah menyebutkan "Dalam Kota". Kategori daring pun ikut: tidak
        // ada perjalanan sama sekali di sana.
        DB::table('kategori_perjadin')
            ->where('nama', 'like', '%Dalam Kota%')
            ->orWhere('nama', 'like', '%Daring%')
            ->orWhere('nama', 'like', '%Transport Lokal%')
            ->update(['dalam_kota' => true]);
    }

    public function down(): void
    {
        Schema::table('kategori_perjadin', function (Blueprint $table): void {
            $table->dropColumn('dalam_kota');
        });
    }
};
