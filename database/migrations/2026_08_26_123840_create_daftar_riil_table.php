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
        Schema::create('daftar_riil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usulan')->constrained('usulan')->cascadeOnDelete();
            $table->foreignId('id_peserta')->constrained('peserta_usulan')->cascadeOnDelete();
            $table->float('total_riil')->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('id_ppk')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditandatangani_at')->nullable();
            $table->timestamps();

            // Satu daftar pengeluaran riil per peserta per usulan.
            $table->unique(['id_usulan', 'id_peserta']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_riil');
    }
};
