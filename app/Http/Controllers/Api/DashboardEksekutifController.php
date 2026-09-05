<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TahunAnggaran;
use App\Services\RingkasanEksekutif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Data dashboard eksekutif untuk dibaca aplikasi lain.
 *
 * Hanya membaca; tidak ada satu pun jalur yang mengubah data. Angkanya
 * berasal dari RingkasanEksekutif — sumber yang sama dengan halaman web —
 * supaya aplikasi pemanggil tidak menampilkan angka yang berbeda dari PANGI.
 */
class DashboardEksekutifController extends Controller
{
    public function __construct(private RingkasanEksekutif $ringkasan) {}

    /**
     * Seluruh isi dashboard eksekutif dalam satu permintaan.
     */
    public function index(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);
        $perKategori = $this->ringkasan->realisasiPerKategori($tahun);

        return $this->balas($tahun, [
            'ringkasan' => $this->angkaRingkasan($tahun),
            'realisasi' => $this->angkaRealisasi($tahun, $perKategori),
            'pegawai' => $this->angkaPegawai($tahun),
        ]);
    }

    /**
     * Kartu ringkasan: total realisasi dan pergerakan pegawai hari ini.
     */
    public function ringkasan(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, ['ringkasan' => $this->angkaRingkasan($tahun)]);
    }

    /**
     * Realisasi anggaran: total, per bulan, dan per kategori perjadin.
     */
    public function realisasi(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, [
            'realisasi' => $this->angkaRealisasi($tahun, $this->ringkasan->realisasiPerKategori($tahun)),
        ]);
    }

    /**
     * Pergerakan pegawai: per bulan dan per unit kerja.
     */
    public function pegawai(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, ['pegawai' => $this->angkaPegawai($tahun)]);
    }

    /**
     * Tahun anggaran yang datanya tersedia, untuk mengisi pilihan tahun
     * pada aplikasi pemanggil.
     */
    public function tahunAnggaran(): JsonResponse
    {
        return response()->json([
            'data' => [
                'aktif' => $this->ringkasan->tahunBawaan(),
                'tersedia' => $this->ringkasan->tahunTersedia()->map(fn ($t) => (int) $t)->all(),
            ],
        ]);
    }

    // ── Penyusun bagian ──

    /**
     * @return array<string, mixed>
     */
    private function angkaRingkasan(int $tahun): array
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
     * @param  Collection<string, list<float>>  $perKategori
     * @return array<string, mixed>
     */
    private function angkaRealisasi(int $tahun, $perKategori): array
    {
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
     * @return array<string, mixed>
     */
    private function angkaPegawai(int $tahun): array
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

    /**
     * Tahun yang diminta, dibatasi rentang wajar agar tahun ngawur tidak
     * memaksa basis data memindai sia-sia.
     */
    private function tahun(Request $request): int
    {
        $diminta = $request->integer('tahun');

        return $diminta >= 2000 && $diminta <= 2100
            ? $diminta
            : $this->ringkasan->tahunBawaan();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function balas(int $tahun, array $data): JsonResponse
    {
        return response()->json([
            'data' => ['tahun' => $tahun, 'bulan' => RingkasanEksekutif::BULAN] + $data,
        ]);
    }
}
