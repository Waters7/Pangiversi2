<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usulan', function (Blueprint $table) {
            $table->id();
            $table->string('no_usulan')->nullable(false);
            $table->string('no_tugas')->nullable(false);
            $table->enum('status', ['draft', 'diajukan', 'menunggu', 'disetujui', 'ditolak', 'selesai'])->default('draft');
            $table->string('lokasi')->nullable(false);
            $table->string('instansi')->nullable(false);
            $table->string('tanggal_mulai')->nullable(false);
            $table->string('tanggal_selesai')->nullable(false);
            $table->longText('uraian')->nullable(true);
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('id_kegiatan')->nullable()->constrained('kegiatan')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usulan');
    }
};
