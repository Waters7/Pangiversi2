<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPK dapat mengembalikan berkas kepada tim keuangan alih-alih
 * menandatanganinya.
 *
 * Alasannya wajib dicatat: tanpa itu tim keuangan hanya tahu berkasnya
 * kembali, bukan apa yang harus diperbaiki. Berkas yang dikembalikan
 * kehilangan validasinya, sehingga harus diperiksa lagi sebelum berjalan
 * kembali ke pelaksana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->timestamp('dikembalikan_at')->nullable()->after('divalidasi_at');
            $table->text('alasan_kembali')->nullable()->after('dikembalikan_at');
            $table->foreignId('id_pengembali')->nullable()->after('alasan_kembali')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_pengembali');
            $table->dropColumn(['dikembalikan_at', 'alasan_kembali']);
        });
    }
};
