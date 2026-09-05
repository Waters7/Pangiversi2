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
        // Penyimpanan kunci–nilai untuk pengaturan yang boleh diubah admin
        // tanpa menyentuh berkas konfigurasi.
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 64)->unique();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
