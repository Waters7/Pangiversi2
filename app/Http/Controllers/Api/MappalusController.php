<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataMappalus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data daftar nominatif yang diambil Mappalus.
 *
 * Mappalus memanggil index, menyimpan datanya, lalu memanggil konfirmasi.
 * Data yang belum dikonfirmasi terus disajikan, jadi sambungan yang putus di
 * tengah jalan tidak menghilangkan apa pun.
 */
class MappalusController extends Controller
{
    private const PER_HALAMAN_BAWAAN = 100;

    private const PER_HALAMAN_MAKS = 200;

    public function __construct(private DataMappalus $data) {}

    public function index(Request $request): JsonResponse
    {
        $semua = $this->data->baris();
        $tersedia = $this->data->tersedia($semua);

        $halaman = max(1, $request->integer('halaman', 1));
        $perHalaman = min(self::PER_HALAMAN_MAKS, max(1, $request->integer('per_halaman', self::PER_HALAMAN_BAWAAN)));
        $isi = $tersedia->forPage($halaman, $perHalaman)->values();

        $this->data->catatDiambil($isi);

        return $this->balas([
            'data' => $isi->map(fn (array $baris) => $baris['data'] + [
                'versi' => $baris['sidik'],
                'status' => $baris['catatan']?->pernahDikonfirmasi() ? 'berubah' : 'baru',
            ])->all(),
            'ditarik' => $semua->where('status', DataMappalus::DITARIK)->pluck('data.referensi')->values()->all(),
            'meta' => [
                'total' => $tersedia->count(),
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'halaman_terakhir' => max(1, (int) ceil($tersedia->count() / $perHalaman)),
            ],
        ]);
    }

    public function konfirmasi(Request $request): JsonResponse
    {
        $masukan = $request->validate([
            'data' => ['required', 'array', 'max:'.self::PER_HALAMAN_MAKS],
            'data.*.referensi' => ['required', 'string', 'max:100'],
            'data.*.versi' => ['required', 'string', 'size:64'],
        ]);

        return $this->balas($this->data->konfirmasi($masukan['data']));
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function balas(array $isi): JsonResponse
    {
        return response()->json($isi)->header('Cache-Control', 'no-store');
    }
}
