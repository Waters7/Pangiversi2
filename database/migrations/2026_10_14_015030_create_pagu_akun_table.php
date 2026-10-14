<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagu per kode akun dalam satu tahun anggaran.
 *
 * Angka maksimal tiap mata anggaran perjalanan dinas, beserta sumber
 * dananya (RM/BLU), mengikuti Rincian Kertas Kerja Satker. Pagu tahun
 * anggaran menjadi jumlah seluruh pagu akunnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagu_akun', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tahun_anggaran')->constrained('tahun_anggaran')->cascadeOnDelete();
            $table->foreignId('id_akun_pembiayaan')->constrained('akun_pembiayaan')->cascadeOnDelete();
            $table->foreignId('id_kategori_pembiayaan')->nullable()->constrained('kategori_pembiayaan')->nullOnDelete();
            $table->decimal('pagu', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['id_tahun_anggaran', 'id_akun_pembiayaan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagu_akun');
    }
};
