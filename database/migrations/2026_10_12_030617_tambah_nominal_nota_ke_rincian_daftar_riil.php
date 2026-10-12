<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nominal nota pelaksana pada penyalinan terakhir, bagi baris transport
     * lokal yang lahir dari nota.
     *
     * Tim keuangan kini dapat mengoreksi nominal itu. Dengan pembanding ini
     * penyalinan ulang hanya menimpa nominal bila pelaksana sendiri mengubah
     * notanya — tanpa itu, koreksi hilang setiap kali pelaksana menyimpan
     * seksi berkas mana pun.
     *
     * Baris lama dibiarkan kosong; penyalinan berikutnya mengisinya.
     */
    public function up(): void
    {
        Schema::table('rincian_daftar_riil', function (Blueprint $table) {
            $table->decimal('nominal_nota', 15, 2)->nullable()->after('nominal');
        });
    }

    public function down(): void
    {
        Schema::table('rincian_daftar_riil', function (Blueprint $table) {
            $table->dropColumn('nominal_nota');
        });
    }
};
