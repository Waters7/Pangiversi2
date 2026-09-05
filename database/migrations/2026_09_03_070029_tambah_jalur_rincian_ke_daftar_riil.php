<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian biaya perjalanan dinas dan daftar pengeluaran riil adalah dua
 * dokumen yang berbeda, jadi masing-masing punya jalur persetujuannya
 * sendiri: pelaksana boleh menyetujui yang satu dan menyanggah yang lain.
 *
 * Kolom tanpa awalan tetap milik daftar riil supaya berkas yang sudah
 * berjalan tidak perlu dipindahkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table): void {
            $table->timestamp('rincian_disetujui_at')->nullable()->after('disanggah_at');
            $table->timestamp('rincian_disanggah_at')->nullable()->after('rincian_disetujui_at');
            $table->text('rincian_sanggahan')->nullable()->after('rincian_disanggah_at');
            $table->string('rincian_kode_konfirmasi')->nullable()->after('rincian_sanggahan');
            $table->timestamp('rincian_ditandatangani_at')->nullable()->after('rincian_kode_konfirmasi');
            $table->unsignedBigInteger('rincian_id_ppk')->nullable()->after('rincian_ditandatangani_at');
            $table->string('rincian_kode_verifikasi')->nullable()->after('rincian_id_ppk');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table): void {
            $table->dropColumn([
                'rincian_disetujui_at',
                'rincian_disanggah_at',
                'rincian_sanggahan',
                'rincian_kode_konfirmasi',
                'rincian_ditandatangani_at',
                'rincian_id_ppk',
                'rincian_kode_verifikasi',
            ]);
        });
    }
};
