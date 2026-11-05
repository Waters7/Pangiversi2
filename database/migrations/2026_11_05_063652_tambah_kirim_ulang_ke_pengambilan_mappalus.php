<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mappalus dapat meminta lagi data yang sudah terkirim. Permintaan itu dicatat
 * terpisah dari waktu kirimnya, supaya rentang waktu yang sama selalu menyajikan
 * data yang sama berapa kali pun diminta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengambilan_mappalus', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_kirim_ulang')->default(0)->after('jumlah_diambil');
            $table->timestamp('kirim_ulang_at')->nullable()->after('diambil_at');
            $table->index('diambil_at');
        });
    }

    public function down(): void
    {
        Schema::table('pengambilan_mappalus', function (Blueprint $table) {
            $table->dropIndex(['diambil_at']);
            $table->dropColumn(['jumlah_kirim_ulang', 'kirim_ulang_at']);
        });
    }
};
