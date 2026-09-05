<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelum PPK membubuhkan tanda tangan, pelaksana perjalanan diberi
     * kesempatan memeriksa nominalnya lebih dulu. Bila keberatan, ia dapat
     * menyanggah selama masa sanggah masih berjalan.
     */
    public function up(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->timestamp('dikirim_ke_pegawai_at')->nullable()->after('keterangan');
            $table->date('batas_sanggah')->nullable()->after('dikirim_ke_pegawai_at');
            $table->timestamp('disetujui_pegawai_at')->nullable()->after('batas_sanggah');
            $table->text('sanggahan')->nullable()->after('disetujui_pegawai_at');
            $table->timestamp('disanggah_at')->nullable()->after('sanggahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daftar_riil', function (Blueprint $table) {
            $table->dropColumn([
                'dikirim_ke_pegawai_at',
                'batas_sanggah',
                'disetujui_pegawai_at',
                'sanggahan',
                'disanggah_at',
            ]);
        });
    }
};
