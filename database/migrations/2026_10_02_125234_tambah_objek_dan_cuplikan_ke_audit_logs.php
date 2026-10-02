<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penghapusan usulan perjadin dan SPD direkam lengkap dengan isinya.
     *
     * Begitu sebuah usulan atau SPD dihapus, barisnya hilang beserta seluruh
     * relasinya — yang tersisa hanya jejak audit. Jenis objek dan cuplikan
     * isinya (nomor, pelaksana, tujuan, tanggal) disimpan di sini supaya
     * riwayat penghapusan tetap terbaca utuh tanpa barang aslinya.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('objek', 30)->nullable()->after('aksi');
            $table->json('cuplikan')->nullable()->after('catatan');

            $table->index(['objek', 'created_at']);
        });

        // Penghapusan usulan sebelum kolom ini ada sudah tercatat dengan
        // deskripsi bakunya; tandai supaya ikut tampil pada riwayatnya.
        DB::table('audit_logs')
            ->where('aksi', 'dihapus')
            ->where('deskripsi', 'like', 'Usulan % dihapus.')
            ->update(['objek' => 'usulan']);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['objek', 'created_at']);
            $table->dropColumn(['objek', 'cuplikan']);
        });
    }
};
