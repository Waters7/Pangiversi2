<?php

namespace App\Console\Commands;

use App\Enums\KategoriBiaya;
use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\Usulan;
use App\Services\PencatatTransportLokal;
use App\Services\PenguncianBerkas;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Menyusulkan nominal berkas pertanggungjawaban yang belum masuk rincian
 * biaya maupun daftar riil — misalnya biaya penyelenggaraan yang dulu
 * ditolak kolom kategori ber-ENUM di MySQL, yang ikut menggagalkan
 * penyalinan nota transport lokal sesudahnya.
 *
 * Sekaligus memindahkan baris rincian berkategori transport lokal — yang
 * dulu tersembunyi dari tabel dan cetakan — ke Daftar Pengeluaran Riil.
 * Berkas yang sudah ditandatangani tidak disentuh.
 */
class SelaraskanBiayaBerkas extends Command
{
    protected $signature = 'pangi:selaraskan-biaya
                            {nomor?* : Nomor usulan tertentu; kosongkan untuk semua yang nominal berkasnya tertinggal}';

    protected $description = 'Salin ulang nominal berkas pelaksana yang belum tercatat pada rincian biaya atau daftar riil';

    public function handle(SinkronBiayaDokumen $sinkron, PenguncianBerkas $kunci, PencatatTransportLokal $transportLokal): int
    {
        $usulan = $this->argument('nomor') !== []
            ? Usulan::whereIn('no_usulan', $this->argument('nomor'))->get()
            : $this->yangTertinggal($sinkron);

        if ($usulan->isEmpty()) {
            $this->info('Tidak ada nominal berkas yang tertinggal dari rincian biaya maupun daftar riil.');

            return self::SUCCESS;
        }

        foreach ($usulan as $satu) {
            $this->pindahkanTransportLokal($satu, $kunci, $transportLokal);

            if ($alasan = $kunci->unggahanPelaksana($satu)) {
                $this->warn("{$satu->no_usulan}: dilewati — {$alasan}");

                continue;
            }

            $hasil = $sinkron->selaraskan($satu->fresh(['tiket', 'notaTransport', 'dokumen', 'laporan', 'keuangan']));
            $this->line("{$satu->no_usulan}: {$hasil['ditambah']} ditambah, {$hasil['diperbarui']} diperbarui, {$hasil['dihapus']} dicabut.");
        }

        return self::SUCCESS;
    }

    /**
     * Baris rincian transport lokal tulisan tim keuangan pindah ke daftar riil.
     */
    private function pindahkanTransportLokal(Usulan $usulan, PenguncianBerkas $kunci, PencatatTransportLokal $transportLokal): void
    {
        $baris = RincianBiaya::where('id_keuangan', $usulan->keuangan?->id)
            ->where('kategori', KategoriBiaya::TransportLokal->value)
            ->where('sumber', RincianBiaya::SUMBER_KEUANGAN)
            ->get();

        if ($baris->isEmpty()) {
            return;
        }

        if ($kunci->rincianBiaya($usulan) || $kunci->daftarRiil($usulan)) {
            $this->warn("{$usulan->no_usulan}: {$baris->count()} baris transport lokal tidak dipindah — berkasnya sudah ditandatangani.");

            return;
        }

        foreach ($baris as $satu) {
            try {
                $transportLokal->pindahkan($usulan, $satu, $satu->komponen, (float) $satu->jumlah);
                $this->line("{$usulan->no_usulan}: \"{$satu->komponen}\" dipindah ke transport lokal.");
            } catch (HttpException $e) {
                $this->warn("{$usulan->no_usulan}: \"{$satu->komponen}\" tidak dipindah — {$e->getMessage()}");
            } catch (ValidationException $e) {
                $this->warn("{$usulan->no_usulan}: \"{$satu->komponen}\" tidak dipindah — ".collect($e->errors())->flatten()->first());
            }
        }
    }

    /**
     * Usulan yang nominal berkasnya belum seluruhnya ada sebagai baris
     * rincian atau daftar riil, atau yang masih menyimpan baris transport
     * lokal di rincian biaya.
     *
     * @return Collection<int, Usulan>
     */
    private function yangTertinggal(SinkronBiayaDokumen $sinkron): Collection
    {
        return Usulan::with('tiket', 'notaTransport', 'dokumen', 'keuangan.rincianBiaya', 'daftarRiil.rincian')
            ->where(fn ($q) => $q->has('tiket')->orHas('dokumen')->orHas('notaTransport')->orWhereHas(
                'keuangan.rincianBiaya',
                fn ($r) => $r->where('kategori', KategoriBiaya::TransportLokal->value),
            ))
            ->get()
            ->filter(function (Usulan $usulan) use ($sinkron): bool {
                $rincian = $usulan->keuangan?->rincianBiaya ?? collect();

                if ($rincian->contains(fn (RincianBiaya $baris) => $baris->kategori === KategoriBiaya::TransportLokal && ! $baris->dariDokumen())) {
                    return true;
                }

                $tercatat = $rincian->where('sumber', RincianBiaya::SUMBER_DOKUMEN)->pluck('kunci_sumber')->all();

                if (array_diff(array_keys($sinkron->barisDariDokumen($usulan)), $tercatat) !== []) {
                    return true;
                }

                $notaTercatat = $usulan->daftarRiil
                    ->flatMap->rincian
                    ->where('sumber', RincianDaftarRiil::SUMBER_DOKUMEN)
                    ->pluck('kunci_sumber')
                    ->all();

                return $usulan->notaTransport
                    ->filter->terisi()
                    ->contains(fn ($nota) => ! in_array('nota:'.$nota->urutan, $notaTercatat, true));
            })
            ->values();
    }
}
