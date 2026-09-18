<?php

namespace App\Services;

use App\Models\TahunAnggaran;
use Illuminate\Support\Collection;

/**
 * Paket data dashboard eksekutif dalam bentuk yang sama bagi API bertoken
 * (ditarik aplikasi lain) maupun pengiriman terjadwal (didorong PANGI ke
 * aplikasi tujuan) — supaya penerima mana pun menerima angka yang persis
 * sama dengan halaman Dashboard Eksekutif.
 */
class PaketDataEksekutif
{
    public function __construct(private RingkasanEksekutif $ringkasan) {}

    /**
     * Seluruh bagian sekaligus, seperti balasan jalur API induk.
     *
     * @return array<string, mixed>
     */
    public function semua(int $tahun): array
    {
        return [
            'ringkasan' => $this->ringkasan($tahun),
            'realisasi' => $this->realisasi($tahun),
            'pegawai' => $this->pegawai($tahun),
        ];
    }

    /**
     * Paket lengkap beserta keterangan tahun dan bulan, siap dikirim.
     *
     * @return array<string, mixed>
     */
    public function kiriman(int $tahun): array
    {
        return [
            'sumber' => 'PANGI',
            'dikirim_pada' => now()->toIso8601String(),
            'data' => ['tahun' => $tahun, 'bulan' => RingkasanEksekutif::BULAN] + $this->semua($tahun),
        ];
    }

    /**
     * Kartu ringkasan: total realisasi dan pergerakan pegawai hari ini.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(int $tahun): array
    {
        $anggaran = TahunAnggaran::firstWhere('tahun', $tahun);
        $realisasi = $this->ringkasan->totalRealisasi($tahun);
        $pagu = (float) ($anggaran?->pagu ?? 0);

        return [
            'pagu' => $pagu,
            'total_realisasi' => $realisasi,
            // Dijaga terhadap pagu nol: tanpa ini pembagiannya melempar galat
            // pada tahun anggaran yang pagunya belum diisi.
            'persentase_realisasi' => $pagu > 0 ? round($realisasi / $pagu * 100, 2) : null,
            'sisa_pagu' => $pagu > 0 ? $pagu - $realisasi : null,
            'akan_berangkat' => $this->ringkasan->akanBerangkat(),
            'sedang_berjalan' => $this->ringkasan->sedangBerjalan(),
            'belum_melapor' => $this->ringkasan->belumMelapor(),
        ];
    }

    /**
     * Realisasi anggaran: total, per bulan, dan per kategori perjadin.
     *
     * @return array<string, mixed>
     */
    public function realisasi(int $tahun): array
    {
        /** @var Collection<string, list<float>> $perKategori */
        $perKategori = $this->ringkasan->realisasiPerKategori($tahun);

        return [
            'total' => $this->ringkasan->totalRealisasi($tahun),
            'per_bulan' => $this->ringkasan->jumlahkanPerBulan($perKategori),
            'per_kategori' => $perKategori
                ->map(fn (array $nilai, string $nama) => [
                    'kategori' => $nama,
                    'per_bulan' => $nilai,
                    'total' => array_sum($nilai),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Pergerakan pegawai: per bulan dan per unit kerja.
     *
     * @return array<string, mixed>
     */
    public function pegawai(int $tahun): array
    {
        return [
            'per_bulan' => $this->ringkasan->pegawaiPerBulan($tahun),
            'per_unit' => $this->ringkasan->pegawaiPerUnit($tahun)
                ->map(fn (array $unit) => [
                    'unit' => $unit['unit'],
                    'orang' => $unit['orang'],
                    'perjalanan' => $unit['perjalanan'],
                    'biaya' => $unit['biaya'],
                    'per_bulan' => $unit['perBulan'],
                ])
                ->all(),
        ];
    }
}
