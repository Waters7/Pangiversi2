<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cara bayar tiap komponen dan penghapusan baris dari berkas pelaksana.
     *
     * Cara bayar menyatakan apakah komponen dibayarkan lewat uang muka atau
     * dibayar pelaksana lebih dahulu lalu diganti saat pelunasan; tim
     * keuangan yang mengonfirmasinya. Kosong berarti belum ditentukan dan
     * dihitung masuk uang muka, seperti sebelumnya.
     *
     * Baris dari berkas pelaksana yang dihapus tim keuangan tidak benar-benar
     * dibuang: ia ditandai terhapus supaya tidak tersalin lagi dari berkasnya
     * setiap kali halaman dibuka, dan dapat dikembalikan bila keliru.
     */
    public function up(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->string('cara_bayar', 20)->nullable()->after('id_validator');
            $table->timestamp('cara_bayar_dikonfirmasi_at')->nullable()->after('cara_bayar');
            $table->foreignId('id_pengonfirmasi_bayar')->nullable()->after('cara_bayar_dikonfirmasi_at')
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->dropForeign(['id_pengonfirmasi_bayar']);
            $table->dropColumn(['cara_bayar', 'cara_bayar_dikonfirmasi_at', 'id_pengonfirmasi_bayar', 'deleted_at']);
        });
    }
};
