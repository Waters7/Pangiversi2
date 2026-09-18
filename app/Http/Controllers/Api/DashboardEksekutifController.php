<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaketDataEksekutif;
use App\Services\RingkasanEksekutif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data dashboard eksekutif untuk dibaca aplikasi lain.
 *
 * Hanya membaca; tidak ada satu pun jalur yang mengubah data. Angkanya
 * berasal dari RingkasanEksekutif — sumber yang sama dengan halaman web —
 * supaya aplikasi pemanggil tidak menampilkan angka yang berbeda dari PANGI.
 */
class DashboardEksekutifController extends Controller
{
    public function __construct(private RingkasanEksekutif $ringkasan, private PaketDataEksekutif $paket) {}

    /**
     * Seluruh isi dashboard eksekutif dalam satu permintaan.
     */
    public function index(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, $this->paket->semua($tahun));
    }

    /**
     * Kartu ringkasan: total realisasi dan pergerakan pegawai hari ini.
     */
    public function ringkasan(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, ['ringkasan' => $this->paket->ringkasan($tahun)]);
    }

    /**
     * Realisasi anggaran: total, per bulan, dan per kategori perjadin.
     */
    public function realisasi(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, ['realisasi' => $this->paket->realisasi($tahun)]);
    }

    /**
     * Pergerakan pegawai: per bulan dan per unit kerja.
     */
    public function pegawai(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return $this->balas($tahun, ['pegawai' => $this->paket->pegawai($tahun)]);
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
