<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Peran beserta hak aksesnya dikelola dari aplikasi: peran bawaan
     * disalin dari enum saat pertama kali dibuka, peran baru ditambahkan
     * super administrator. Kolom `role` pada users tetap menyimpan kodenya.
     */
    public function up(): void
    {
        Schema::create('peran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 60)->unique();
            $table->string('nama', 100);
            $table->string('keterangan')->nullable();
            $table->boolean('bawaan')->default(false);
            $table->timestamps();
        });

        Schema::create('hak_akses_peran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_peran')->constrained('peran')->cascadeOnDelete();
            $table->string('kemampuan', 60);
            $table->timestamps();

            $table->unique(['id_peran', 'kemampuan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hak_akses_peran');
        Schema::dropIfExists('peran');
    }
};
