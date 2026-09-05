<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Validasi kini cukup satu tahap di PPK, sehingga seluruh usulan yang
     * masih menunggu atasan maupun pimpinan dialihkan ke antrian PPK.
     *
     * @var list<string>
     */
    private const STATUS_MENUNGGU_LAMA = ['menunggu_atasan', 'menunggu_sdm', 'menunggu_direktur', 'menunggu_pimpinan'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('usulan')
            ->whereIn('status', self::STATUS_MENUNGGU_LAMA)
            ->update(['status' => 'menunggu_ppk']);

        // Keputusan atasan dan pimpinan tidak lagi punya tahap padanan.
        DB::table('persetujuan')->whereIn('peran', ['atasan', 'pimpinan', 'direktur', 'sdm'])->delete();

        DB::table('persetujuan')->update(['level' => 1, 'peran' => 'ppk']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tahap yang dihapus tidak dapat dipulihkan; status dikembalikan ke
        // antrian atasan sebagai titik awal rantai berjenjang sebelumnya.
        DB::table('usulan')
            ->where('status', 'menunggu_ppk')
            ->update(['status' => 'menunggu_atasan']);
    }
};
