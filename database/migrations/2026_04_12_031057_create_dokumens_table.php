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
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('surat_tugas')->nullable(false);
            $table->string('rundown')->nullable(true);
            $table->string('dokumen_pendukung')->nullable(true);
            $table->string('sppd')->nullable(true);
            $table->string('boarding_pass')->nullable(true);
            $table->string('faktur')->nullable(true);
            $table->string('kwintasi')->nullable(true);
            $table->string('bill_hotel')->nullable(true);
            $table->string('laporan_hasil')->nullable(true);
            $table->foreignId('id_usulan')->constrained('usulan')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen');
    }
};
