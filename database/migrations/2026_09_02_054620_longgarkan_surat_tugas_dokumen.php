<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Longgarkan dokumen.surat_tugas agar boleh kosong.
 *
 * Baris dokumen kini dapat lahir dari seksi mana pun formulir
 * pertanggungjawaban — akomodasi, misalnya, bisa diisi lebih dulu. Selama
 * kolomnya NOT NULL, urutan pengisian yang tidak biasa berujung galat basis
 * data, bukan pesan yang dapat dibaca pengguna.
 *
 * Kewajiban mengunggah surat tugas tetap ditegakkan pada validasi formulir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->string('surat_tugas')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->string('surat_tugas')->nullable(false)->change();
        });
    }
};
