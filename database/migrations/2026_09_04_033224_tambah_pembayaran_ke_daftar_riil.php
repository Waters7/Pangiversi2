<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penggantian transport lokal dibayarkan dan dicatat sendiri.
 *
 * Sebelumnya ia hanya menumpang pada pelunasan, sehingga tidak ada tanggal
 * maupun bukti transfer yang khusus menerangkannya. Padahal nominalnya
 * berdiri sendiri — berasal dari Daftar Pengeluaran Riil, bukan dari
 * rincian biaya — dan kerap ditransfer terpisah.
 *
 * Yang belum dibayarkan lewat jalur ini tetap ikut pada pelunasan, jadi
 * berkas lama tidak berubah perlakuannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table): void {
            $table->date('dibayar_at')->nullable()->after('rincian_kode_verifikasi');
            $table->string('bukti_bayar')->nullable()->after('dibayar_at');
            $table->foreignId('id_pembayar')->nullable()->after('bukti_bayar')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('id_pembayar');
            $table->dropColumn(['dibayar_at', 'bukti_bayar']);
        });
    }
};
