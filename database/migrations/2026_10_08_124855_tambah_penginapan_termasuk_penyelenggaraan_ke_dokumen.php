<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelaksana menyatakan biaya penyelenggaraan sudah termasuk penginapan.
     *
     * Bila ya, bill hotel tidak ditagih lagi dan uang penginapan tidak
     * disalin ke rincian biaya — kalau tidak, hotel yang sama terbayar dua
     * kali: lewat biaya penyelenggaraan dan lewat uang penginapan.
     */
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->boolean('penyelenggaraan_termasuk_penginapan')->default(false)->after('penyelenggaraan_ada');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->dropColumn('penyelenggaraan_termasuk_penginapan');
        });
    }
};
