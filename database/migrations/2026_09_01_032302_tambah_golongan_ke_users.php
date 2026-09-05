<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Golongan kepegawaian, dipakai menyusun kolom "Pangkat dan Golongan"
 * pada Surat Perjalanan Dinas.
 *
 * Yang disimpan hanya golongan dan ruangnya, misalnya III/a. Nama pangkatnya
 * diturunkan dari enum Golongan sehingga tidak ada dua sumber kebenaran yang
 * bisa berselisih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('golongan', 8)->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('golongan');
        });
    }
};
