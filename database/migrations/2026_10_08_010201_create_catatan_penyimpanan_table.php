<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ukuran aplikasi di peladen, satu catatan per hari.
     *
     * Diukur penjadwal tiap malam dan setiap kali super administrator
     * menekan Ukur Ulang pada menu Pemantauan Server, sehingga pertumbuhan
     * berkas unggahan, basis data, dan log dapat diikuti dari hari ke hari
     * sebelum kuota hosting habis.
     */
    public function up(): void
    {
        Schema::create('catatan_penyimpanan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->unsignedBigInteger('total_byte');
            $table->unsignedBigInteger('unggahan_byte')->default(0);
            $table->unsignedBigInteger('basis_data_byte')->default(0);
            $table->json('rincian');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_penyimpanan');
    }
};
