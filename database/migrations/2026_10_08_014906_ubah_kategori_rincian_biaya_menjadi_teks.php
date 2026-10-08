<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori rincian biaya dilepas dari ENUM menjadi teks.
     *
     * Kolom ini dibuat sebagai ENUM lima nilai sebelum kategori Biaya
     * Penyelenggaraan ada. MySQL lalu menolak nilai "penyelenggaraan":
     * menyunting baris ke kategori itu berujung galat 500, dan nominal
     * biaya penyelenggaraan dari berkas pelaksana gagal masuk ke rincian.
     * Daftar kategori yang sah dijaga KategoriBiaya di aplikasi, jadi
     * penambahan kategori berikutnya tidak lagi butuh perubahan skema.
     */
    public function up(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->string('kategori', 30)->default('lainnya')->change();
        });
    }

    public function down(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->enum('kategori', ['transport', 'uang_harian', 'transport_lokal', 'penginapan', 'penyelenggaraan', 'lainnya'])
                ->default('lainnya')
                ->change();
        });
    }
};
