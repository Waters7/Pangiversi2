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
        Schema::table('usulan', function (Blueprint $table) {
            $table->foreignId('id_tahun_anggaran')->nullable()->after('id_kegiatan')->constrained('tahun_anggaran')->nullOnDelete();
            $table->foreignId('id_lokasi')->nullable()->after('lokasi')->constrained('lokasi_tujuan')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_lokasi');
            $table->dropConstrainedForeignId('id_tahun_anggaran');
        });
    }
};
