<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saluran bantuan: pengguna melaporkan kendala, administrator menjawabnya.
 *
 * Bentuknya percakapan, bukan formulir sekali kirim — kendala jarang selesai
 * dalam satu pesan, dan riwayat tanya jawabnya justru bagian yang berguna
 * saat kendala yang sama muncul lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obrolan_bantuan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_pelapor')->constrained('users')->cascadeOnDelete();
            $table->string('judul');

            // terbuka | dijawab | selesai
            $table->string('status', 20)->default('terbuka');

            // Perjalanan dinas yang dipersoalkan, bila kendalanya memang
            // menyangkut satu berkas tertentu.
            $table->foreignId('id_usulan')->nullable()->constrained('usulan')->nullOnDelete();

            $table->timestamp('dibalas_at')->nullable();
            $table->timestamp('diselesaikan_at')->nullable();
            $table->foreignId('id_penyelesai')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });

        Schema::create('pesan_bantuan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_obrolan')->constrained('obrolan_bantuan')->cascadeOnDelete();
            $table->foreignId('id_pengirim')->constrained('users')->cascadeOnDelete();
            $table->text('isi');
            $table->string('lampiran')->nullable();

            // Dibaca oleh pihak seberang, bukan oleh pengirimnya sendiri.
            $table->timestamp('dibaca_at')->nullable();

            $table->timestamps();

            $table->index(['id_obrolan', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan_bantuan');
        Schema::dropIfExists('obrolan_bantuan');
    }
};
