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
        Schema::create('lokasi_tujuan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('provinsi')->nullable();
            $table->enum('jenis', ['dalam_kota', 'luar_kota', 'luar_negeri'])->default('luar_kota');
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lokasi_tujuan');
    }
};
