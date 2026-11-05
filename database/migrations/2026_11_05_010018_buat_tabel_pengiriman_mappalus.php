<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan kiriman ke Mappalus: satu baris per pelaksana per perjalanan.
 *
 * Kolom data dan sidik merekam percobaan terakhir, sedangkan sidik_diterima
 * merekam isi yang terakhir diterima Mappalus. Dari keduanya terbedakan data
 * yang belum, gagal, sudah, atau berubah sesudah terkirim, sehingga data yang
 * sudah diterima tidak terkirim dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengiriman_mappalus', function (Blueprint $table) {
            $table->id();
            $table->string('referensi', 100)->unique();
            $table->foreignId('id_usulan')->nullable()->constrained('usulan')->nullOnDelete();
            $table->foreignId('id_nominatif')->nullable()->constrained('daftar_nominatif')->nullOnDelete();
            $table->string('tim_kerja');
            $table->string('kode_akun', 50);
            $table->decimal('total_nominal', 15, 2);
            $table->string('keterangan', 500);
            $table->string('sidik', 64);
            $table->string('sidik_diterima', 64)->nullable();
            $table->unsignedInteger('percobaan')->default(0);
            $table->unsignedSmallInteger('kode_http')->nullable();
            $table->text('pesan')->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamp('diterima_at')->nullable();
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengiriman_mappalus');
    }
};
