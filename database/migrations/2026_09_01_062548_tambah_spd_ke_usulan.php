<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kaitkan usulan perjalanan dinas dengan SPD yang menjadi dasarnya.
 *
 * Boleh kosong agar usulan lama — yang dibuat sebelum SPD menjadi syarat —
 * tetap dapat dibaca. Bila SPD-nya dihapus, kaitannya dilepas, bukan
 * usulannya ikut terhapus: pertanggungjawaban perjalanan tetap harus ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan', function (Blueprint $table): void {
            $table->foreignId('id_spd')
                ->nullable()
                ->after('id_kategori_perjadin')
                ->constrained('surat_perjalanan_dinas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table): void {
            $table->dropForeign(['id_spd']);
            $table->dropColumn('id_spd');
        });
    }
};
