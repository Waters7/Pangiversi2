<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris rincian pada Daftar Pengeluaran Riil (Lampiran IX PMK 113/2012).
 *
 * Sebelumnya daftar ini hanya menyimpan satu angka total, sehingga dokumen
 * cetaknya tidak dapat memuat tabel "No. | Rincian Biaya | Jumlah" seperti
 * format bakunya. Biaya transport lokal masuk ke sini, bukan ke rincian biaya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rincian_daftar_riil', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_daftar_riil')->constrained('daftar_riil')->cascadeOnDelete();

            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('uraian');
            $table->decimal('nominal', 15, 2)->default(0);

            // 'dokumen' bila lahir dari nota pelaksana, 'manual' bila diketik
            // tim keuangan. Menentukan baris mana yang boleh ditimpa saat
            // pelaksana memperbaiki notanya.
            $table->string('sumber', 20)->default('manual');
            $table->string('kunci_sumber')->nullable();

            $table->timestamps();

            $table->unique(['id_daftar_riil', 'kunci_sumber']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rincian_daftar_riil');
    }
};
