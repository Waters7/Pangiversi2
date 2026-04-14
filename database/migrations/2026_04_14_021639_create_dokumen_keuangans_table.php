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
        Schema::create('dokumen_keuangan', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_uang_muka');
            $table->string('transfer_sisa');
            $table->foreignId('id_keuangan')->constrained('keuangan')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_keuangan');
    }
};
