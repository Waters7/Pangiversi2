<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Dua hak akses baru dipecah dari yang sudah ada, jadi peran yang hak
     * aksesnya sudah tersimpan diberi keduanya menurut hak lamanya — supaya
     * tidak ada yang tiba-tiba kehilangan kemampuan yang selama ini ia pakai:
     *
     * - menghapus komponen rincian biaya, bagi yang menginput rincian biaya;
     * - melihat menu Persetujuan, bagi yang menandatanganinya.
     *
     * Peran yang belum pernah disimpan memakai hak bawaan enumnya, yang sudah
     * memuat keduanya.
     */
    private const TURUNAN = [
        'mengelola-biaya' => 'menghapus-rincian-biaya',
        'menandatangani-daftar-riil' => 'melihat-persetujuan',
    ];

    public function up(): void
    {
        foreach (self::TURUNAN as $asal => $baru) {
            $peran = DB::table('hak_akses_peran')->where('kemampuan', $asal)->pluck('id_peran');

            DB::table('hak_akses_peran')->insertOrIgnore($peran->map(fn ($id) => [
                'id_peran' => $id,
                'kemampuan' => $baru,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }
    }

    public function down(): void
    {
        DB::table('hak_akses_peran')->whereIn('kemampuan', array_values(self::TURUNAN))->delete();
    }
};
