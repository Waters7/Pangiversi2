<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar nominatif dikelompokkan menurut akun pembiayaan yang dibebani,
 * supaya tim keuangan langsung tahu dari mata anggaran mana pembayarannya
 * keluar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->foreignId('id_akun_pembiayaan')
                ->nullable()
                ->after('id_kategori_pembiayaan')
                ->constrained('akun_pembiayaan')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_akun_pembiayaan');
        });
    }
};
