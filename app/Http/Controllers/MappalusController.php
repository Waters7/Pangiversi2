<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LogApi;
use App\Services\AksesMappalus;
use App\Services\AuditService;
use App\Services\DataMappalus;
use App\Services\RekapArsipXlsx;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Integrasi Mappalus — bagian Administrasi Sistem bagi super administrator:
 * token dan batas alamat API, data yang tersedia beserta yang sudah diambil,
 * dan riwayat permintaan Mappalus.
 */
class MappalusController extends Controller
{
    private const BARIS_PER_HALAMAN = 25;

    private const BARIS_RIWAYAT = 20;

    private const JALUR_API = '/api/v1/mappalus/%';

    public function __construct(
        private DataMappalus $data,
        private AksesMappalus $akses,
        private AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->pastikanAdmin($request);

        $semua = $this->data->baris();
        $integrasi = $this->ikutIntegrasi($semua);
        $tampil = $this->saring($integrasi, $request);

        $halaman = max(1, $request->integer('page', 1));
        $tokenBaru = $request->session()->get('token_mappalus_baru');

        return view('administrasi.mappalus', [
            'token' => $this->akses->keadaanToken(),
            'tokenBaru' => $tokenBaru ? Crypt::decryptString($tokenBaru) : null,
            'alamat' => $this->akses->daftarAlamat(),
            'status' => $this->statusTerpilih($request),
            'cari' => trim((string) $request->input('cari')),
            'jumlah' => $integrasi->countBy('status')->all(),
            'total' => $integrasi->count(),
            'belumLengkap' => $semua->count() - $integrasi->count(),
            'baris' => new LengthAwarePaginator(
                $tampil->forPage($halaman, self::BARIS_PER_HALAMAN)->values(),
                $tampil->count(),
                self::BARIS_PER_HALAMAN,
                $halaman,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'riwayat' => LogApi::where('jalur', 'like', self::JALUR_API)->latest('id')->limit(self::BARIS_RIWAYAT)->get(),
            'ditolakTujuhHari' => LogApi::where('jalur', 'like', self::JALUR_API)
                ->sejak(now()->subDays(7))
                ->where('hasil', '!=', LogApi::DITERIMA)
                ->count(),
        ]);
    }

    /**
     * Seluruh baris pada saringan yang sedang dipilih, bukan hanya satu halaman.
     */
    public function ekspor(Request $request, RekapArsipXlsx $rekap): Response
    {
        $this->pastikanAdmin($request);

        $berkas = $rekap->mappalus($this->saring($this->ikutIntegrasi($this->data->baris()), $request))->keString();

        return response($berkas, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Integrasi-Mappalus-'.now()->format('Y-m-d').'.xlsx"',
            'Content-Length' => (string) strlen($berkas),
        ]);
    }

    /**
     * Terbitkan token baru. Tokennya tampil sekali pada halaman berikutnya;
     * selama menunggu di sesi ia dienkripsi, sebab sesinya tersimpan di
     * basis data.
     */
    public function buatToken(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $sebelumnya = $this->akses->terbuka();
        $token = $this->akses->buatToken();

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, $sebelumnya ? 'Token API Mappalus diganti.' : 'Token API Mappalus dibuat.');

        return redirect()->route('administrasi.mappalus')
            ->with('token_mappalus_baru', Crypt::encryptString($token))
            ->with('success', 'Token baru terbit. Salin sekarang; token ini tidak dapat ditampilkan lagi.');
    }

    public function cabutToken(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $this->akses->cabutToken();

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, 'Token API Mappalus dicabut.');

        return redirect()->route('administrasi.mappalus')->with('success', 'Token dicabut. API Mappalus tertutup sampai token baru dibuat.');
    }

    /**
     * Simpan alamat yang boleh memanggil API, satu alamat IP atau CIDR per baris.
     */
    public function simpanAlamat(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $alamat = collect(preg_split('/[\s,]+/', (string) $request->input('alamat_ip'), flags: PREG_SPLIT_NO_EMPTY))->unique()->values();

        $request->merge(['alamat' => $alamat->all()]);
        $request->validate(['alamat' => ['array', 'max:50'], 'alamat.*' => [$this->aturanAlamat()]]);

        $this->akses->simpanDaftarAlamat($alamat->all());

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $alamat->isEmpty() ? 'Batas alamat API Mappalus dikosongkan.' : 'Batas alamat API Mappalus diubah: '.$alamat->implode(', ').'.',
        );

        return redirect()->route('administrasi.mappalus')->with('success', 'Batas alamat tersimpan.');
    }

    /**
     * Pelaksana yang tim kerja dan kode akunnya belum diisi tidak ikut integrasi.
     *
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return Collection<int, array<string, mixed>>
     */
    private function ikutIntegrasi(Collection $baris): Collection
    {
        return $baris->reject(fn (array $item) => $item['status'] === DataMappalus::BELUM_LENGKAP);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     * @return Collection<int, array<string, mixed>>
     */
    private function saring(Collection $baris, Request $request): Collection
    {
        $status = $this->statusTerpilih($request);
        $cari = mb_strtolower(trim((string) $request->input('cari')));

        return $baris
            ->when($status, fn (Collection $isi) => $isi->where('status', $status))
            ->when($cari !== '', fn (Collection $isi) => $isi->filter(
                fn (array $item) => str_contains(mb_strtolower($item['data']['keterangan'].' '.$item['data']['referensi']), $cari)
            ))
            ->values();
    }

    private function statusTerpilih(Request $request): ?string
    {
        return array_key_exists((string) $request->input('status'), DataMappalus::LABEL) ? $request->input('status') : null;
    }

    private function aturanAlamat(): Closure
    {
        return function (string $atribut, mixed $nilai, Closure $gagal): void {
            [$ip, $prefiks] = array_pad(explode('/', (string) $nilai, 2), 2, null);
            $batas = str_contains((string) $ip, ':') ? 128 : 32;

            if (filter_var($ip, FILTER_VALIDATE_IP) === false
                || ($prefiks !== null && (! ctype_digit($prefiks) || (int) $prefiks > $batas))) {
                $gagal("Alamat {$nilai} bukan alamat IP atau CIDR yang sah.");
            }
        };
    }

    private function pastikanAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Integrasi Mappalus hanya untuk super administrator.');
    }
}
