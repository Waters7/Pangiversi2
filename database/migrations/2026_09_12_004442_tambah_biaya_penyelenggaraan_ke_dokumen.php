<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya penyelenggaraan (kontribusi/registrasi kegiatan) yang dibayar
 * pelaksana: ada atau tidak, nominalnya, bukti bayar, dan nomor invoice
 * bila ada. Ikut ke rincian biaya di bawah uang penginapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            // Null berarti belum dijawab; false berarti pelaksana menyatakan
            // tidak ada biaya penyelenggaraan.
            $table->boolean('penyelenggaraan_ada')->nullable()->after('bill_hotel_nominal');
            $table->decimal('penyelenggaraan_nominal', 15, 2)->nullable()->after('penyelenggaraan_ada');
            $table->string('penyelenggaraan_invoice', 100)->nullable()->after('penyelenggaraan_nominal');
            $table->string('penyelenggaraan_bukti')->nullable()->after('penyelenggaraan_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->dropColumn([
                'penyelenggaraan_ada',
                'penyelenggaraan_nominal',
                'penyelenggaraan_invoice',
                'penyelenggaraan_bukti',
            ]);
        });
    }
};
