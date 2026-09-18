<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua catatan untuk menu Integrasi Data: setiap permintaan yang masuk ke
 * API bertoken (diterima maupun ditolak) dan setiap pengiriman data
 * terjadwal ke aplikasi tujuan. Keduanya hanya bertambah, tidak pernah
 * diubah, sehingga tidak butuh updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_api', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->string('metode', 8);
            $table->string('jalur', 191);
            // diterima | ditolak | tertutup
            $table->string('hasil', 16)->index();
            // Token yang dibawa pemanggil, hanya ujung-ujungnya.
            $table->string('token_tersamar', 32)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedInteger('durasi_ms')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('pengiriman_integrasi', function (Blueprint $table) {
            $table->id();
            // jadwal | manual
            $table->string('pemicu', 8);
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tujuan', 255);
            // berhasil | gagal
            $table->string('status', 12)->index();
            $table->unsignedSmallInteger('kode_http')->nullable();
            $table->text('pesan')->nullable();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('ukuran_byte')->nullable();
            $table->unsignedInteger('durasi_ms')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengiriman_integrasi');
        Schema::dropIfExists('log_api');
    }
};
