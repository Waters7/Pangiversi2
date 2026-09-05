<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tahap SDM dihapus dari rantai persetujuan, sehingga usulan yang sedang
     * menunggu SDM diteruskan ke PPK. Tahap direktur berganti nama menjadi
     * pimpinan tanpa mengubah posisinya.
     *
     * @var array<string, string>
     */
    private const PEMETAAN_STATUS = [
        'menunggu_sdm' => 'menunggu_ppk',
        'menunggu_direktur' => 'menunggu_pimpinan',
    ];

    /**
     * Level pada riwayat keputusan ikut bergeser: PPK dari 3 menjadi 2 dan
     * pimpinan dari 4 menjadi 3.
     *
     * @var array<int, int>
     */
    private const PEMETAAN_LEVEL = [
        3 => 2,
        4 => 3,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::PEMETAAN_STATUS as $lama => $baru) {
            DB::table('usulan')->where('status', $lama)->update(['status' => $baru]);
        }

        // Keputusan SDM yang terlanjur tercatat tidak lagi punya tahap padanan.
        DB::table('persetujuan')->where('level', 2)->delete();

        foreach (self::PEMETAAN_LEVEL as $lama => $baru) {
            DB::table('persetujuan')->where('level', $lama)->update(['level' => $baru]);
        }

        DB::table('persetujuan')->where('peran', 'sdm')->update(['peran' => 'ppk']);
        DB::table('persetujuan')->where('peran', 'direktur')->update(['peran' => 'pimpinan']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('persetujuan')->where('peran', 'pimpinan')->update(['peran' => 'direktur']);

        foreach (array_reverse(self::PEMETAAN_LEVEL, true) as $lama => $baru) {
            DB::table('persetujuan')->where('level', $baru)->update(['level' => $lama]);
        }

        foreach (self::PEMETAAN_STATUS as $lama => $baru) {
            DB::table('usulan')->where('status', $baru)->update(['status' => $lama]);
        }
    }
};
