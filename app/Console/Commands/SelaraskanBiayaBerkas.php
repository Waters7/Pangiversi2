<?php

namespace App\Console\Commands;

use App\Models\RincianBiaya;
use App\Models\Usulan;
use App\Services\PenguncianBerkas;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Menyusulkan nominal berkas pertanggungjawaban yang belum masuk rincian
 * biaya — misalnya biaya penyelenggaraan yang dulu ditolak kolom kategori
 * ber-ENUM di MySQL. Rincian yang sudah ditandatangani tidak disentuh.
 */
class SelaraskanBiayaBerkas extends Command
{
    protected $signature = 'pangi:selaraskan-biaya
                            {nomor?* : Nomor usulan tertentu; kosongkan untuk semua yang nominal berkasnya tertinggal}';

    protected $description = 'Salin ulang nominal berkas pelaksana yang belum tercatat pada rincian biaya';

    public function handle(SinkronBiayaDokumen $sinkron, PenguncianBerkas $kunci): int
    {
        $usulan = $this->argument('nomor') !== []
            ? Usulan::whereIn('no_usulan', $this->argument('nomor'))->get()
            : $this->yangTertinggal($sinkron);

        if ($usulan->isEmpty()) {
            $this->info('Tidak ada nominal berkas yang tertinggal dari rincian biaya.');

            return self::SUCCESS;
        }

        foreach ($usulan as $satu) {
            if ($alasan = $kunci->rincianBiaya($satu)) {
                $this->warn("{$satu->no_usulan}: dilewati — {$alasan}");

                continue;
            }

            $hasil = $sinkron->selaraskan($satu->fresh(['tiket', 'notaTransport', 'dokumen', 'laporan', 'keuangan']));
            $this->line("{$satu->no_usulan}: {$hasil['ditambah']} ditambah, {$hasil['diperbarui']} diperbarui, {$hasil['dihapus']} dicabut.");
        }

        return self::SUCCESS;
    }

    /**
     * Usulan yang nominal berkasnya belum seluruhnya ada sebagai baris rincian.
     *
     * @return Collection<int, Usulan>
     */
    private function yangTertinggal(SinkronBiayaDokumen $sinkron): Collection
    {
        return Usulan::with('tiket', 'notaTransport', 'dokumen', 'keuangan.rincianBiaya')
            ->where(fn ($q) => $q->has('tiket')->orHas('dokumen'))
            ->get()
            ->filter(function (Usulan $usulan) use ($sinkron): bool {
                $tercatat = ($usulan->keuangan?->rincianBiaya ?? collect())
                    ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
                    ->pluck('kunci_sumber')
                    ->all();

                return array_diff(array_keys($sinkron->barisDariDokumen($usulan)), $tercatat) !== [];
            })
            ->values();
    }
}
