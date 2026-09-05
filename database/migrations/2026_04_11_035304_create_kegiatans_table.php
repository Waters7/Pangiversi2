<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Berkas ini pernah bernama 2026_04_11_040624_create_kegiatans_table dan
     * diurutkan ulang agar berjalan sebelum tabel usulan yang merujuknya —
     * MySQL menolak kunci asing ke tabel yang belum ada, sementara SQLite
     * membiarkannya. Peladen yang sudah menjalankan versi lama mencatat nama
     * berkas yang lama, sehingga berkas ini terbaca sebagai migrasi baru.
     * Penjagaan di bawah membuatnya aman dijalankan di sana.
     */
    public function up(): void
    {
        if (Schema::hasTable('kegiatan')) {
            return;
        }

        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
