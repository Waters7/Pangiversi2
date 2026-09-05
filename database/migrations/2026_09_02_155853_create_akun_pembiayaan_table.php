<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun pembiayaan — mata anggaran yang membebani perjalanan dinas.
 *
 * Berbeda dari kategori pembiayaan (RM/BLU/LN) yang menyatakan sumber
 * dananya: akun menyatakan mata anggaran mana yang dibebani, misalnya
 * 524111 untuk belanja perjalanan dinas biasa. Daftar nominatif
 * dikelompokkan menurut akun ini saat diteruskan ke tim keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_pembiayaan', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 60)->unique();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Akun yang lazim dipakai satuan kerja, supaya menu ini tidak kosong
        // saat pertama dibuka.
        $baris = [
            ['kode' => '524111', 'nama' => 'Belanja Perjalanan Dinas Biasa', 'urutan' => 1],
            ['kode' => '524113', 'nama' => 'Belanja Perjalanan Dinas Dalam Kota', 'urutan' => 2],
            ['kode' => '524114', 'nama' => 'Belanja Perjalanan Dinas Paket Meeting Dalam Kota', 'urutan' => 3],
            ['kode' => '524119', 'nama' => 'Belanja Perjalanan Dinas Paket Meeting Luar Kota', 'urutan' => 4],
        ];

        foreach ($baris as $isi) {
            DB::table('akun_pembiayaan')->insert($isi + [
                'is_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('akun_pembiayaan');
    }
};
