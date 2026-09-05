<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori pembiayaan ditetapkan tim keuangan, bukan diisi pengusul.
 *
 * Karena itu kolomnya boleh kosong: usulan baru belum punya kategori sampai
 * tim keuangan menentukannya saat menyusun daftar nominatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan', function (Blueprint $table): void {
            $table->foreignId('id_kategori_pembiayaan')->nullable()->after('id_kategori_perjadin')
                ->constrained('kategori_pembiayaan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table): void {
            $table->dropForeign(['id_kategori_pembiayaan']);
            $table->dropColumn('id_kategori_pembiayaan');
        });
    }
};
