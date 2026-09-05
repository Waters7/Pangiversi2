<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan perjalanan dinas yang disusun langsung di aplikasi.
 *
 * Sebelumnya pelaksana mengunduh format kosong, mengetiknya di luar, lalu
 * mengunggah hasilnya — isinya tidak pernah terbaca sistem. Dengan disimpan
 * di sini, kegiatan dan rencana tindak lanjutnya dapat direkap dan ditagih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_perjadin', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_usulan')->unique()->constrained('usulan')->cascadeOnDelete();

            $table->text('dasar')->nullable();
            $table->text('hasil')->nullable();
            $table->text('kesimpulan')->nullable();

            // Terisi saat pelaksana menyatakan laporannya selesai; sejak itu
            // dokumennya dapat dicetak dan dihitung sebagai berkas lengkap.
            $table->timestamp('diselesaikan_at')->nullable();

            $table->timestamps();
        });

        Schema::create('laporan_kegiatan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_laporan')->constrained('laporan_perjadin')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->text('uraian');
            $table->timestamps();
        });

        Schema::create('tindak_lanjut', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_laporan')->constrained('laporan_perjadin')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->text('uraian');
            $table->string('penanggung_jawab')->nullable();
            $table->date('target_selesai')->nullable();

            // 'rencana', 'berjalan', atau 'selesai' — lihat App\Enums\StatusTindakLanjut.
            $table->string('status', 20)->default('rencana');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindak_lanjut');
        Schema::dropIfExists('laporan_kegiatan');
        Schema::dropIfExists('laporan_perjadin');
    }
};
