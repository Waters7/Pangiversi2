<?php

namespace App\Services;

use App\Enums\KategoriBiaya;
use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\Usulan;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Memulihkan rincian biaya yang tertinggal dari berkas pelaksana.
 *
 * Nominal yang diisi pelaksana — tiket, bill hotel, biaya penyelenggaraan,
 * nota transport lokal — harus selalu tampil pada tabelnya supaya dapat
 * diperiksa dan dikoreksi tim keuangan. Barisnya bisa tertinggal: terhapus,
 * atau penyalinannya gagal di tengah jalan (seperti saat kolom kategori
 * MySQL masih ber-ENUM). Baris berkategori transport lokal yang dulu
 * tersembunyi di rincian biaya sekaligus dipindah ke daftar riil.
 */
class PemulihRincian
{
    public function __construct(
        private SinkronBiayaDokumen $sinkron,
        private PenguncianBerkas $kunci,
        private PencatatTransportLokal $transportLokal,
    ) {}

    /**
     * Ada nominal berkas yang belum tercatat, atau baris transport lokal
     * yang tersembunyi di rincian biaya.
     */
    public function perlu(Usulan $usulan): bool
    {
        $usulan->loadMissing('tiket', 'notaTransport', 'dokumen', 'keuangan.rincianBiaya', 'daftarRiil.rincian');
        $rincian = $usulan->keuangan?->rincianBiaya ?? collect();

        if ($rincian->contains(fn (RincianBiaya $baris) => $baris->kategori === KategoriBiaya::TransportLokal && ! $baris->dariDokumen())) {
            return true;
        }

        $tercatat = $rincian->where('sumber', RincianBiaya::SUMBER_DOKUMEN)->pluck('kunci_sumber')->all();

        if (array_diff(array_keys($this->sinkron->barisDariDokumen($usulan)), $tercatat) !== []) {
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
    }

    /**
     * @return list<string> Catatan tiap tindakan, untuk ditampilkan.
     */
    public function pulihkan(Usulan $usulan): array
    {
        $catatan = $this->pindahkanTransportLokal($usulan);

        if ($alasan = $this->kunci->unggahanPelaksana($usulan)) {
            $catatan[] = "dilewati — {$alasan}";

            return $catatan;
        }

        $hasil = $this->sinkron->selaraskan($usulan->fresh(['tiket', 'notaTransport', 'dokumen', 'laporan', 'keuangan']));
        $catatan[] = "{$hasil['ditambah']} ditambah, {$hasil['diperbarui']} diperbarui, {$hasil['dihapus']} dicabut";

        return $catatan;
    }

    /**
     * @return list<string>
     */
    private function pindahkanTransportLokal(Usulan $usulan): array
    {
        $baris = RincianBiaya::where('id_keuangan', $usulan->keuangan?->id)
            ->where('kategori', KategoriBiaya::TransportLokal->value)
            ->where('sumber', RincianBiaya::SUMBER_KEUANGAN)
            ->get();

        if ($baris->isEmpty()) {
            return [];
        }

        if ($this->kunci->rincianBiaya($usulan) || $this->kunci->daftarRiil($usulan)) {
            return ["{$baris->count()} baris transport lokal tidak dipindah — berkasnya sudah ditandatangani"];
        }

        $catatan = [];

        foreach ($baris as $satu) {
            try {
                $this->transportLokal->pindahkan($usulan, $satu, $satu->komponen, (float) $satu->jumlah);
                $catatan[] = "\"{$satu->komponen}\" dipindah ke transport lokal";
            } catch (HttpException $e) {
                $catatan[] = "\"{$satu->komponen}\" tidak dipindah — {$e->getMessage()}";
            } catch (ValidationException $e) {
                $catatan[] = "\"{$satu->komponen}\" tidak dipindah — ".collect($e->errors())->flatten()->first();
            }
        }

        return $catatan;
    }
}
