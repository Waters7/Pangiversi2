<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan perjalanan dinas dikirim pelaksana kepada pimpinan untuk
 * dikonfirmasi dan ditandatangani, atau dikembalikan bila perlu direvisi.
 *
 * Kedua pihak meninggalkan kode konfirmasi yang tercetak sebagai QR pada
 * dokumennya: kode pelaksana terbit saat laporan dikirim, kode pimpinan saat
 * laporan dikonfirmasi. Konfirmasi pimpinan inilah salah satu syarat
 * pelunasan pembayaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->timestamp('dikirim_at')->nullable()->after('diselesaikan_at');
            $table->string('kode_pelaksana', 20)->nullable()->unique()->after('dikirim_at');

            $table->timestamp('dikonfirmasi_at')->nullable()->after('kode_pelaksana');
            $table->foreignId('id_pimpinan')->nullable()->after('dikonfirmasi_at')
                ->constrained('users')->nullOnDelete();
            $table->string('kode_pimpinan', 20)->nullable()->unique()->after('id_pimpinan');

            $table->timestamp('dikembalikan_at')->nullable()->after('kode_pimpinan');
            $table->text('catatan_pimpinan')->nullable()->after('dikembalikan_at');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pimpinan');
            $table->dropUnique(['kode_pelaksana']);
            $table->dropUnique(['kode_pimpinan']);
            $table->dropColumn([
                'dikirim_at', 'kode_pelaksana', 'dikonfirmasi_at',
                'kode_pimpinan', 'dikembalikan_at', 'catatan_pimpinan',
            ]);
        });
    }
};
