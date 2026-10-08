<?php

namespace App\Services;

use App\Enums\IsianBiaya;
use App\Models\RincianBiaya;
use App\Models\Usulan;
use Illuminate\Support\Collection;

/**
 * Menentukan siapa yang memegang nominal tiap komponen yang dapat diisi dua
 * pihak: tiket pergi, tiket pulang, bill hotel, dan biaya penyelenggaraan.
 *
 * Tim keuangan yang lebih dulu menetapkan nominalnya mengunci isian
 * pelaksana pada angka itu; pelaksana yang lebih dulu mengisi nominal
 * beserta buktinya menutup komponen itu bagi tim keuangan. Hasilnya satu
 * komponen hanya punya satu baris pada rincian biaya.
 */
class PemegangNominal
{
    /**
     * Isian yang nominalnya ditetapkan tim keuangan, beserta baris-baris
     * rincian yang menetapkannya.
     *
     * @return array<string, Collection<int, RincianBiaya>>
     */
    public function dariKeuangan(Usulan $usulan): array
    {
        $usulan->loadMissing('keuangan.rincianBiaya');

        $hasil = [];

        foreach ($usulan->keuangan?->rincianBiaya ?? [] as $baris) {
            foreach ($baris->isianYangDiwakili() as $isian) {
                $hasil[$isian->value][] = $baris;
            }
        }

        return array_map(fn (array $baris) => collect($baris), $hasil);
    }

    /**
     * Isian yang sudah diisi pelaksana — nominal beserta buktinya.
     *
     * @return array<string, float>
     */
    public function dariPelaksana(Usulan $usulan): array
    {
        $usulan->loadMissing('tiket', 'dokumen');

        $hasil = [];

        foreach ($usulan->tiket as $tiket) {
            if ($tiket->harga > 0 && (filled($tiket->invoice) || filled($tiket->boarding_pass))) {
                $hasil[IsianBiaya::dariArah($tiket->arah)->value] = (float) $tiket->harga;
            }
        }

        $dokumen = $usulan->dokumen->last();

        if ($dokumen?->bill_hotel_nominal > 0 && filled($dokumen->bill_hotel)) {
            $hasil[IsianBiaya::Penginapan->value] = (float) $dokumen->bill_hotel_nominal;
        }

        if ($dokumen?->adaPenyelenggaraan() && $dokumen->penyelenggaraan_nominal > 0 && filled($dokumen->penyelenggaraan_bukti)) {
            $hasil[IsianBiaya::Penyelenggaraan->value] = (float) $dokumen->penyelenggaraan_nominal;
        }

        return $hasil;
    }

    public function ditetapkanKeuangan(Usulan $usulan, IsianBiaya $isian): bool
    {
        return isset($this->dariKeuangan($usulan)[$isian->value]);
    }

    /**
     * Isian yang hendak diwakili baris tim keuangan tetapi sudah diisi
     * pelaksana — baris itu tidak boleh ditambahkan.
     *
     * @param  IsianBiaya|null  $sebelumnya  Isian yang sudah diwakili baris
     *                                       yang sedang disunting: tetap
     *                                       boleh dipertahankan.
     * @return list<IsianBiaya>
     */
    public function bentrokDenganPelaksana(Usulan $usulan, ?IsianBiaya $isian, ?IsianBiaya $sebelumnya = null): array
    {
        if ($isian === null) {
            return [];
        }

        $pelaksana = $this->dariPelaksana($usulan);
        $dipertahankan = $sebelumnya?->mencakup() ?? [];

        return array_values(array_filter(
            $isian->mencakup(),
            fn (IsianBiaya $satu) => isset($pelaksana[$satu->value]) && ! in_array($satu, $dipertahankan, true),
        ));
    }

    /**
     * Pesan penolakan bila tim keuangan menulis komponen yang sudah diisi pelaksana.
     *
     * @param  list<IsianBiaya>  $bentrok
     */
    public function pesanBentrok(Usulan $usulan, array $bentrok): string
    {
        $pelaksana = $this->dariPelaksana($usulan);

        $uraian = collect($bentrok)
            ->map(fn (IsianBiaya $isian) => $isian->label().' (Rp '.number_format($pelaksana[$isian->value] ?? 0, 0, ',', '.').')')
            ->implode(' dan ');

        return "{$uraian} sudah diisi pelaksana beserta buktinya, jadi tidak ditambahkan lagi agar tidak tercatat dua kali. "
            .'Periksa dan validasi baris dari pelaksana itu, atau koreksi nominalnya di sana.';
    }

    /**
     * Komponen yang tercatat dua kali pada rincian biaya — sisa data dari
     * sebelum aturan ini berlaku — supaya tim keuangan menghapus salah satunya.
     *
     * @return list<string>
     */
    public function catatanGanda(Usulan $usulan): array
    {
        $usulan->loadMissing('keuangan.rincianBiaya');
        $rincian = $usulan->keuangan?->rincianBiaya ?? collect();

        $dariBerkas = $rincian->filter(fn (RincianBiaya $baris) => $baris->dariDokumen())
            ->keyBy('kunci_sumber');
        $tulisanKeuangan = $rincian->reject(fn (RincianBiaya $baris) => $baris->dariDokumen());

        $catatan = [];

        foreach (IsianBiaya::isianPelaksana() as $isian) {
            $baris = $dariBerkas->get($isian->value);

            if (! $baris) {
                continue;
            }

            $kembaran = $tulisanKeuangan->filter(fn (RincianBiaya $tulisan) => in_array($isian, $tulisan->isianYangDiwakili(), true)
                || ($tulisan->isian_pelaksana === null && $tulisan->kategori === $baris->kategori));

            if ($kembaran->isNotEmpty()) {
                $catatan[] = $isian->label().' tercatat dari berkas pelaksana (Rp '.number_format($baris->jumlah, 0, ',', '.').') '
                    .'dan dari tim keuangan ('.$kembaran->map(fn (RincianBiaya $tulisan) => '"'.$tulisan->komponen.'" Rp '.number_format($tulisan->jumlah, 0, ',', '.'))->implode(', ').') '
                    .'— hapus salah satunya bila keduanya untuk komponen yang sama.';
            }
        }

        return $catatan;
    }
}
