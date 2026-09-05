<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar nominatif terbit per surat tugas, bukan per usulan: satu surat
 * tugas dapat memberangkatkan beberapa pelaksana, dan pembayarannya
 * disahkan sekaligus dalam satu daftar.
 *
 * Barisnya tidak disimpan — ia diturunkan dari usulan yang berbagi nomor
 * surat tugas, sehingga angka pada daftar tidak pernah berbeda dari angka
 * pada rincian biaya yang sudah divalidasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daftar_nominatif', function (Blueprint $table) {
            $table->id();
            $table->string('no_tugas')->unique();
            $table->date('tanggal_tugas')->nullable();

            $table->foreignId('id_kategori_pembiayaan')->nullable()->constrained('kategori_pembiayaan')->nullOnDelete();

            $table->foreignId('id_ppk')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditandatangani_at')->nullable();
            $table->timestamp('dikirim_sdm_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daftar_nominatif');
    }
};
