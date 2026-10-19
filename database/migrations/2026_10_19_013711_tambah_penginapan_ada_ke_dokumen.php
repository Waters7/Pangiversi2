<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban pelaksana luar kota dalam satu provinsi atas pertanyaan "apakah
 * ada biaya penginapan?". Kosong berarti belum dijawab — bill hotel tetap
 * ditagih seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->boolean('penginapan_ada')->nullable()->after('bill_hotel_nominal');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->dropColumn('penginapan_ada');
        });
    }
};
