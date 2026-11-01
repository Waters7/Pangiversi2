<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor laporan perjalanan dinas (LPD-tahun-urut) dan rujukan ke laporan
 * rekan yang isinya dipakai — boleh bila surat tugas dan lokasi tugasnya
 * sama.
 *
 * Laporan yang sudah ada diberi nomor menurut tahun dibuatnya, berurutan
 * dari yang paling lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->string('nomor_laporan', 20)->nullable()->unique()->after('id_usulan');
            $table->foreignId('id_laporan_acuan')->nullable()->after('nomor_laporan')
                ->constrained('laporan_perjadin')->nullOnDelete();
        });

        $urut = [];

        DB::table('laporan_perjadin')->select(['id', 'created_at'])->lazyById()->each(function (object $laporan) use (&$urut): void {
            $tahun = $laporan->created_at ? Carbon::parse($laporan->created_at)->format('Y') : now()->format('Y');
            $urut[$tahun] = ($urut[$tahun] ?? 0) + 1;

            DB::table('laporan_perjadin')->where('id', $laporan->id)->update([
                'nomor_laporan' => 'LPD-'.$tahun.'-'.str_pad((string) $urut[$tahun], 4, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_laporan_acuan');
            $table->dropUnique(['nomor_laporan']);
            $table->dropColumn('nomor_laporan');
        });
    }
};
