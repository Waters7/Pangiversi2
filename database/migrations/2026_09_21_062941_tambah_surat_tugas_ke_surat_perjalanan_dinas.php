<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat tugas dilampirkan sejak SPD dibuat, supaya usulan perjadin yang
 * mengacu pada SPD itu tidak perlu mengunggah dan menyalin nomornya lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_perjalanan_dinas', function (Blueprint $table): void {
            $table->string('no_tugas')->nullable()->after('keterangan_lain');
            $table->string('surat_tugas')->nullable()->after('no_tugas');
        });
    }

    public function down(): void
    {
        Schema::table('surat_perjalanan_dinas', function (Blueprint $table): void {
            $table->dropColumn(['no_tugas', 'surat_tugas']);
        });
    }
};
