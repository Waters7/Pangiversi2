<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usulan perjadin wajib membawa SPD yang sudah ditandatangani.
 *
 * Nomor resminya terbit dari SRIKANDI saat SPD diregistrasi dan berkasnya
 * diunggah pengusul; keduanya menjadi dasar persetujuan PPK yang tercatat
 * pada jejak audit. Karena tiap orang mengunggah SPD-nya sendiri, pengajuan
 * berkelompok tidak lagi tersedia — kolom lamanya dibiarkan demi data yang
 * sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->string('no_spd')->nullable()->after('no_tugas');
        });

        Schema::table('dokumen', function (Blueprint $table) {
            $table->string('spd_ditandatangani')->nullable()->after('surat_tugas');
        });
    }

    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->dropColumn('no_spd');
        });

        Schema::table('dokumen', function (Blueprint $table) {
            $table->dropColumn('spd_ditandatangani');
        });
    }
};
