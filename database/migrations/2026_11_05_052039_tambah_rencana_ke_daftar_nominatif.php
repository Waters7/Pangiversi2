<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda surat tugas yang daftar nominatifnya belum terbit tetapi sudah
 * dapat dibebani lewat menu Pembebanan Anggaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->boolean('rencana')->default(false)->index()->after('no_tugas');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->dropIndex(['rencana']);
            $table->dropColumn('rencana');
        });
    }
};
