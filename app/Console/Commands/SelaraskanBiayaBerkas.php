<?php

namespace App\Console\Commands;

use App\Models\Usulan;
use App\Services\PemulihRincian;
use Illuminate\Console\Command;

/**
 * Menyusulkan nominal berkas pertanggungjawaban yang belum masuk rincian
 * biaya maupun daftar riil, dan memindahkan baris transport lokal yang
 * dulu tersembunyi di rincian biaya — untuk seluruh usulan sekaligus.
 * Halaman rincian biaya memulihkan usulannya sendiri saat dibuka.
 */
class SelaraskanBiayaBerkas extends Command
{
    protected $signature = 'pangi:selaraskan-biaya
                            {nomor?* : Nomor usulan tertentu; kosongkan untuk semua yang nominal berkasnya tertinggal}';

    protected $description = 'Salin ulang nominal berkas pelaksana yang belum tercatat pada rincian biaya atau daftar riil';

    public function handle(PemulihRincian $pemulih): int
    {
        $usulan = $this->argument('nomor') !== []
            ? Usulan::whereIn('no_usulan', $this->argument('nomor'))->get()
            : Usulan::where(fn ($q) => $q->has('tiket')->orHas('dokumen')->orHas('notaTransport')->orHas('keuangan.rincianBiaya'))
                ->get()
                ->filter(fn (Usulan $satu) => $pemulih->perlu($satu))
                ->values();

        if ($usulan->isEmpty()) {
            $this->info('Tidak ada nominal berkas yang tertinggal dari rincian biaya maupun daftar riil.');

            return self::SUCCESS;
        }

        foreach ($usulan as $satu) {
            foreach ($pemulih->pulihkan($satu) as $catatan) {
                $this->line("{$satu->no_usulan}: {$catatan}.");
            }
        }

        return self::SUCCESS;
    }
}
