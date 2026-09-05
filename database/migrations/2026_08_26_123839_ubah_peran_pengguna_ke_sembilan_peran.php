<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pemetaan peran lama ke struktur sembilan peran yang baru.
     *
     * @var array<string, string>
     */
    private const PEMETAAN_MAJU = [
        'administrator' => 'super_administrator',
        'direktur' => 'pimpinan',
        'keuangan' => 'bendahara',
        'sdm' => 'tim_sdm',
        'pegawai' => 'dosen_tendik',
    ];

    /**
     * @var array<string, string>
     */
    private const PEMETAAN_MUNDUR = [
        'super_administrator' => 'administrator',
        'pimpinan' => 'direktur',
        'bendahara' => 'keuangan',
        'tim_keuangan' => 'keuangan',
        'tim_sdm' => 'sdm',
        'dosen_tendik' => 'pegawai',
        'pegawai_eksternal' => 'pegawai',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::PEMETAAN_MAJU as $lama => $baru) {
            DB::table('users')->where('role', $lama)->update(['role' => $baru]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::PEMETAAN_MUNDUR as $baru => $lama) {
            DB::table('users')->where('role', $baru)->update(['role' => $lama]);
        }
    }
};
