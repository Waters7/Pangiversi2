<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor surat SPD tidak boleh dipakai dua kali.
 *
 * Nomornya diambil dari buku agenda dan menjadi rujukan resmi sebuah
 * perjalanan dinas — dua surat bernomor sama membuat arsip tidak dapat
 * ditelusuri. Penjagaan di formulir dapat dilewati oleh dua orang yang
 * menyimpan bersamaan, jadi kuncinya ditanam di basis data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spd_pelaksana', function (Blueprint $table): void {
            $table->unique('nomor_surat', 'spd_pelaksana_nomor_surat_unik');
        });
    }

    public function down(): void
    {
        Schema::table('spd_pelaksana', function (Blueprint $table): void {
            $table->dropUnique('spd_pelaksana_nomor_surat_unik');
        });
    }
};
