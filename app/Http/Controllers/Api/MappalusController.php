<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengambilanMappalus;
use App\Services\DataMappalus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Data daftar nominatif yang diambil Mappalus.
 *
 * Mappalus memanggil index, menyimpan datanya, lalu memanggil konfirmasi.
 * Data yang belum dikonfirmasi terus disajikan, jadi sambungan yang putus di
 * tengah jalan tidak menghilangkan apa pun. Data yang sudah terkirim dapat
 * diminta lagi menurut waktu kirimnya lewat terkirim.
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
        $isi = $this->halamanIni($tersedia, $request);

        $this->data->catatDiambil($isi);

        return $this->balas([
            'data' => $isi->map(fn (array $baris) => $baris['data'] + [
                'versi' => $baris['sidik'],
                'status' => $baris['catatan']?->pernahDikonfirmasi() ? 'berubah' : 'baru',
            ])->all(),
            'ditarik' => $semua->where('status', DataMappalus::DITARIK)->pluck('data.referensi')->values()->all(),
            'meta' => $this->meta($tersedia, $request),
        ]);
    }

    /**
     * Kirim lagi data yang sudah terkirim pada rentang waktu tertentu, misalnya
     * bila Mappalus kehilangan datanya. Isinya persis versi yang dulu dikirim.
     * Data yang sudah ditarik tidak disajikan, hanya disebut referensinya.
     */
    public function terkirim(Request $request): JsonResponse
    {
        $masukan = $request->validate([
            'dari' => ['required', 'date'],
            'sampai' => ['required', 'date', 'after_or_equal:dari'],
        ]);

        $sekarang = $this->data->baris();
        $status = $sekarang->pluck('status', 'data.referensi');
        $ditarik = $sekarang->where('status', DataMappalus::DITARIK)->pluck('data.referensi')->flip();

        [$berlaku, $tertarik] = $this->data
            ->terkirim($this->waktu($masukan['dari']), $this->waktu($masukan['sampai'], akhirHari: true))
            ->partition(fn (PengambilanMappalus $item) => ! $ditarik->has($item->referensi));

        $berlaku = $berlaku->values();
        $isi = $this->halamanIni($berlaku, $request);

        $this->data->catatKirimUlang($isi);

        return $this->balas([
            'data' => $isi->map(fn (PengambilanMappalus $item) => [
                'referensi' => $item->referensi,
                'tim_kerja' => $item->tim_kerja,
                'kode_akun' => $item->kode_akun,
                'total_nominal' => (float) $item->total_nominal,
                'keterangan' => $item->keterangan,
                'versi' => $item->sidik,
                'status' => $status[$item->referensi] ?? DataMappalus::SUDAH_DIAMBIL,
                'dikirim_at' => $item->diambil_at->toIso8601String(),
                'dikonfirmasi_at' => $item->dikonfirmasi_at?->toIso8601String(),
            ])->all(),
            'ditarik' => $tertarik->pluck('referensi')->values()->all(),
            'meta' => $this->meta($berlaku, $request),
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
     * Tanggal tanpa jam pada akhir rentang berarti sampai akhir hari itu.
     * Waktu berzona diubah ke zona aplikasi, yang dipakai catatan kirimannya.
     */
    private function waktu(string $nilai, bool $akhirHari = false): CarbonInterface
    {
        $waktu = Carbon::parse($nilai)->setTimezone((string) config('app.timezone'));

        return $akhirHari && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($nilai)) === 1 ? $waktu->endOfDay() : $waktu;
    }

    /**
     * @param  Collection<int, mixed>  $baris
     * @return Collection<int, mixed>
     */
    private function halamanIni(Collection $baris, Request $request): Collection
    {
        return $baris->forPage($this->nomorHalaman($request), $this->perHalaman($request))->values();
    }

    /**
     * @param  Collection<int, mixed>  $baris
     * @return array{total: int, halaman: int, per_halaman: int, halaman_terakhir: int}
     */
    private function meta(Collection $baris, Request $request): array
    {
        $perHalaman = $this->perHalaman($request);

        return [
            'total' => $baris->count(),
            'halaman' => $this->nomorHalaman($request),
            'per_halaman' => $perHalaman,
            'halaman_terakhir' => max(1, (int) ceil($baris->count() / $perHalaman)),
        ];
    }

    private function nomorHalaman(Request $request): int
    {
        return max(1, $request->integer('halaman', 1));
    }

    private function perHalaman(Request $request): int
    {
        return min(self::PER_HALAMAN_MAKS, max(1, $request->integer('per_halaman', self::PER_HALAMAN_BAWAAN)));
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function balas(array $isi): JsonResponse
    {
        return response()->json($isi)->header('Cache-Control', 'no-store');
    }
}
