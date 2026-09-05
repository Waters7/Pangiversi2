<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal dan status validasi tiap baris rincian biaya.
 *
 * Nominal yang diketik pelaksana pada berkas pertanggungjawaban masuk ke sini
 * sebagai usulan angka, belum sebagai angka resmi. Tanpa penanda ini, angka
 * dari pelaksana tidak dapat dibedakan dari angka yang sudah diperiksa tim
 * keuangan — padahal hanya yang terakhir itu yang boleh dibayarkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table): void {
            // 'keuangan' (diketik tim keuangan) atau 'dokumen' (dari pelaksana).
            $table->string('sumber', 20)->default('keuangan')->after('keterangan');

            // Kunci sinkronisasi: satu baris per rincian dokumen, sehingga
            // pelaksana yang memperbaiki nominalnya memperbarui baris yang
            // sama, bukan menambah baris kembar.
            $table->string('kunci_sumber')->nullable()->after('sumber');

            $table->timestamp('divalidasi_at')->nullable()->after('kunci_sumber');
            $table->foreignId('id_validator')->nullable()->after('divalidasi_at')
                ->constrained('users')->nullOnDelete();

            $table->unique(['id_keuangan', 'kunci_sumber']);
        });
    }

    public function down(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table): void {
            $table->dropUnique(['id_keuangan', 'kunci_sumber']);
            $table->dropForeign(['id_validator']);
            $table->dropColumn(['sumber', 'kunci_sumber', 'divalidasi_at', 'id_validator']);
        });
    }
};
