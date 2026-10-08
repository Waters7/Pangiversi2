<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi berkas pelaksana pada penyalinan terakhir untuk baris yang lahir
     * dari berkas pertanggungjawaban.
     *
     * Dengan pembanding ini penyalinan ulang hanya menulis kolom yang memang
     * diubah pelaksana. Sebelumnya setiap penyalinan menimpa seluruh baris,
     * sehingga koreksi tim keuangan atas nominal pelaksana hilang begitu
     * pelaksana menyimpan seksi berkas mana pun.
     *
     * Baris lama dibiarkan kosong: penyalinan berikutnya mengadopsinya apa
     * adanya — termasuk koreksi yang sudah dibuat tim keuangan.
     */
    public function up(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->json('isi_berkas')->nullable()->after('kunci_sumber');
        });
    }

    public function down(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->dropColumn('isi_berkas');
        });
    }
};
