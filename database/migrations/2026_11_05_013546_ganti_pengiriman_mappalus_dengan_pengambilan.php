<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mappalus kini mengambil sendiri datanya lewat API PANGI, bukan dikirimi.
 *
 * Tabel kiriman dan pengaturan alamat serta token Mappalus tidak terpakai
 * lagi dan dihapus. Sebagai gantinya satu baris per pelaksana mencatat isi
 * yang terakhir diambil dan yang sudah dikonfirmasi Mappalus; dari keduanya
 * terbedakan data yang belum diambil, sudah diambil, sudah dikonfirmasi, atau
 * berubah sesudah itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pengiriman_mappalus');

        DB::table('pengaturan')->whereIn('kunci', ['mappalus_aktif', 'mappalus_url', 'mappalus_token'])->delete();

        Schema::create('pengambilan_mappalus', function (Blueprint $table) {
            $table->id();
            $table->string('referensi', 100)->unique();
            $table->foreignId('id_usulan')->nullable()->constrained('usulan')->nullOnDelete();
            $table->foreignId('id_nominatif')->nullable()->constrained('daftar_nominatif')->nullOnDelete();
            $table->string('tim_kerja');
            $table->string('kode_akun', 50);
            $table->decimal('total_nominal', 15, 2);
            $table->string('keterangan', 500);
            $table->string('sidik', 64);
            $table->string('sidik_dikonfirmasi', 64)->nullable();
            $table->unsignedInteger('jumlah_diambil')->default(0);
            $table->timestamp('pertama_diambil_at')->nullable();
            $table->timestamp('diambil_at')->nullable();
            $table->timestamp('dikonfirmasi_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Tabel kiriman tidak dibuat ulang: isinya sudah dihapus, dan mekanisme
     * kirim yang memakainya sudah tidak ada.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengambilan_mappalus');
    }
};
