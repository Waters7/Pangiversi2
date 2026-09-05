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
            // Satu nomor pengajuan dipakai bersama oleh seluruh peserta kelompok.
            $table->enum('jenis_pengajuan', ['personal', 'kelompok'])->default('personal')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->dropColumn('jenis_pengajuan');
        });
    }
};
