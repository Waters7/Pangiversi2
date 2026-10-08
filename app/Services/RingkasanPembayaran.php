<?php

namespace App\Services;

use App\Enums\CaraBayarBiaya;
use App\Enums\KategoriBiaya;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\Usulan;
use Illuminate\Support\Collection;

/**
 * Siapa membayar komponen mana, dan pada tahap apa.
 *
 * Dipakai tabel status bayar pada detail keuangan dan kartu ringkasan pada
 * konfirmasi pembayaran uang muka, supaya bendahara melihat seluruh komponen
 * yang dibayarkannya tanpa menghitung sendiri.
 */
class RingkasanPembayaran
{
    /**
     * @return array{
     *     komponen: list<array{baris: RincianBiaya, cara: ?CaraBayarBiaya, di_muka: float, pelunasan: float, status: string, sudah_dibayar: bool}>,
     *     menunggu: Collection<int, RincianBiaya>,
     *     uang_harian: float,
     *     uang_harian_di_muka: float,
     *     uang_harian_pelunasan: float,
     *     di_muka: float,
     *     jumlah_di_muka: int,
     *     penggantian: float,
     *     jumlah_penggantian: int,
     *     nominal_menunggu: float,
     *     transport_lokal: float,
     *     belum_dikonfirmasi: int,
     *     selisih_sisa: float,
     * }
     */
    public function untuk(Usulan $usulan): array
    {
        $keuangan = $usulan->keuangan;

        $rincian = ($keuangan?->rincianBiaya ?? collect())
            ->reject(fn (RincianBiaya $baris) => $baris->kategori === KategoriBiaya::TransportLokal)
            ->values();

        $komponen = $rincian->filter->terhitung()
            ->map(fn (RincianBiaya $baris) => $this->komponen($keuangan, $baris))
            ->values();

        $uangHarian = $komponen->whereNull('cara');
        $lewatUangMuka = $komponen->where('cara', CaraBayarBiaya::UangMuka);
        $penggantian = $komponen->where('cara', CaraBayarBiaya::Penggantian);
        $menunggu = $rincian->reject->terhitung()->values();

        return [
            'komponen' => $komponen->all(),
            'menunggu' => $menunggu,
            'uang_harian' => (float) $uangHarian->sum(fn (array $isi) => $isi['baris']->jumlah),
            'uang_harian_di_muka' => (float) $uangHarian->sum('di_muka'),
            'uang_harian_pelunasan' => (float) $uangHarian->sum('pelunasan'),
            'di_muka' => (float) $lewatUangMuka->sum('di_muka'),
            'jumlah_di_muka' => $lewatUangMuka->count(),
            'penggantian' => (float) $penggantian->sum('pelunasan'),
            'jumlah_penggantian' => $penggantian->count(),
            'nominal_menunggu' => (float) $menunggu->sum('jumlah'),
            'transport_lokal' => (float) $usulan->daftarRiil->sum('total_riil'),
            'belum_dikonfirmasi' => $komponen
                ->filter(fn (array $isi) => $isi['cara'] !== null && $isi['baris']->cara_bayar_dikonfirmasi_at === null)
                ->count(),

            // Uang muka yang sudah ditransfer tidak bergeser, jadi koreksi
            // sesudahnya jatuh pada sisa bayar tanpa menjadi milik satu
            // komponen pun. Selisih inilah yang disebutkan pada tabel.
            'selisih_sisa' => round((float) ($keuangan?->sisa ?? 0) - (float) $komponen->sum('pelunasan'), 2),
        ];
    }

    /**
     * @return array{baris: RincianBiaya, cara: ?CaraBayarBiaya, di_muka: float, pelunasan: float, status: string, sudah_dibayar: bool}
     */
    private function komponen(?Keuangan $keuangan, RincianBiaya $baris): array
    {
        $cara = $baris->caraBayar();
        $porsi = Keuangan::PORSI_UANG_HARIAN_DI_MUKA;

        [$diMuka, $pelunasan] = match ($cara) {
            null => [$baris->jumlah * $porsi, $baris->jumlah * (1 - $porsi)],
            CaraBayarBiaya::UangMuka => [$baris->jumlah, 0.0],
            CaraBayarBiaya::Penggantian => [0.0, $baris->jumlah],
        };

        $transfer = $keuangan?->tanggal_transfer?->translatedFormat('d M Y');
        $lunas = $keuangan?->sudahLunas() ? ($keuangan->tanggal_pelunasan?->translatedFormat('d M Y') ?? '') : null;

        [$status, $sudah] = match ($cara) {
            null => match (true) {
                $lunas !== null => [trim("Lunas — 80% uang muka {$transfer}, 20% pelunasan {$lunas}"), true],
                $transfer !== null => ["80% dibayarkan {$transfer}; 20% menunggu pelunasan", false],
                default => ['80% lewat uang muka, 20% saat pelunasan', false],
            },
            CaraBayarBiaya::UangMuka => $transfer !== null
                ? ["Sudah dibayarkan — uang muka {$transfer}", true]
                : ['Menunggu transfer uang muka', false],
            CaraBayarBiaya::Penggantian => $lunas !== null
                ? [trim("Sudah diganti — pelunasan {$lunas}"), true]
                : ['Menunggu pelunasan — masuk sisa bayar', false],
        };

        return [
            'baris' => $baris,
            'cara' => $cara,
            'di_muka' => (float) $diMuka,
            'pelunasan' => (float) $pelunasan,
            'status' => $status,
            'sudah_dibayar' => $sudah,
        ];
    }
}
