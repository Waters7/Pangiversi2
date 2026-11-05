<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Services\AuditService;
use App\Services\SinkronMappalus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Integrasi Mappalus — bagian Administrasi Sistem bagi super administrator:
 * alamat dan token dari pengembang Mappalus, status kiriman tiap pelaksana,
 * dan tombol sinkron manual.
 */
class MappalusController extends Controller
{
    private const BARIS_PER_HALAMAN = 25;

    public function __construct(private SinkronMappalus $sinkron, private AuditService $audit) {}

    public function index(Request $request): View
    {
        $this->pastikanAdmin($request);

        $semua = $this->sinkron->baris();
        $status = array_key_exists((string) $request->input('status'), SinkronMappalus::LABEL) ? $request->input('status') : null;
        $cari = trim((string) $request->input('cari'));

        $tampil = $semua
            ->when($status, fn (Collection $baris) => $baris->where('status', $status))
            ->when($cari !== '', fn (Collection $baris) => $baris->filter(
                fn (array $item) => str_contains(mb_strtolower($item['data']['keterangan'].' '.$item['data']['referensi']), mb_strtolower($cari))
            ))
            ->values();

        $halaman = (int) max(1, $request->integer('page', 1));

        return view('administrasi.mappalus', [
            'pengaturan' => $this->sinkron->pengaturan(),
            'siap' => $this->sinkron->siap(),
            'status' => $status,
            'cari' => $cari,
            'jumlah' => $semua->countBy('status')->all(),
            'total' => $semua->count(),
            'antrean' => $semua->whereIn('status', [SinkronMappalus::SIAP, SinkronMappalus::GAGAL])->count(),
            'baris' => new LengthAwarePaginator(
                $tampil->forPage($halaman, self::BARIS_PER_HALAMAN)->values(),
                $tampil->count(),
                self::BARIS_PER_HALAMAN,
                $halaman,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
        ]);
    }

    /**
     * Simpan alamat dan token dari pengembang Mappalus. Kolom token yang
     * kosong berarti token tersimpan dibiarkan.
     */
    public function simpan(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        $data = $request->validate([
            'mappalus_aktif' => ['nullable', 'boolean'],
            'mappalus_url' => ['nullable', 'url:http,https', 'max:255', Rule::requiredIf($request->boolean('mappalus_aktif'))],
            'mappalus_token' => ['nullable', 'string', 'max:500', 'regex:/^\S+$/'],
            'hapus_mappalus_token' => ['nullable', 'boolean'],
        ], [
            'mappalus_url.required' => 'Alamat API wajib diisi bila pengiriman otomatis diaktifkan.',
            'mappalus_url.url' => 'Alamat API harus berupa URL lengkap yang diawali http:// atau https://.',
            'mappalus_token.regex' => 'Token tidak boleh memuat spasi.',
        ]);

        Pengaturan::simpan([
            Pengaturan::MAPPALUS_AKTIF => $request->boolean('mappalus_aktif') ? '1' : '0',
            Pengaturan::MAPPALUS_URL => trim((string) ($data['mappalus_url'] ?? '')),
        ]);

        if ($request->boolean('hapus_mappalus_token')) {
            Pengaturan::simpanRahasia(Pengaturan::MAPPALUS_TOKEN, '');
        } elseif (filled($data['mappalus_token'] ?? null)) {
            Pengaturan::simpanRahasia(Pengaturan::MAPPALUS_TOKEN, $data['mappalus_token']);
        }

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            'Pengaturan integrasi Mappalus diubah; pengiriman otomatis '.($request->boolean('mappalus_aktif') ? 'aktif' : 'nonaktif').'.',
        );

        return redirect()->route('administrasi.mappalus')->with('success', 'Pengaturan Mappalus tersimpan.');
    }

    /**
     * Sinkron manual: kirim yang berstatus Siap Kirim dan Gagal.
     */
    public function sinkronkan(Request $request): RedirectResponse
    {
        $this->pastikanAdmin($request);

        if (! $this->sinkron->siap()) {
            return back()->with('error', 'Isi dulu alamat API dan token dari pengembang Mappalus.');
        }

        $hasil = $this->sinkron->sinkron($request->user());

        if ($hasil === null) {
            return back()->with('error', 'Sinkron lain sedang berjalan. Coba lagi beberapa saat lagi.');
        }

        $ringkas = "{$hasil['berhasil']} data berhasil, {$hasil['gagal']} gagal".($hasil['sisa'] > 0 ? ", {$hasil['sisa']} belum terkirim" : '').'.';

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, "Sinkron manual Mappalus: {$ringkas}");

        return back()->with($hasil['gagal'] > 0 ? 'error' : 'success', 'Sinkron selesai: '.$ringkas);
    }

    /**
     * Kirim ulang satu pelaksana yang gagal, atau yang datanya berubah
     * sesudah diterima Mappalus.
     */
    public function kirimUlang(Request $request, string $referensi): RedirectResponse
    {
        $this->pastikanAdmin($request);

        if (! $this->sinkron->siap()) {
            return back()->with('error', 'Isi dulu alamat API dan token dari pengembang Mappalus.');
        }

        $catatan = $this->sinkron->kirimUlang($referensi, $request->user());

        if ($catatan === null) {
            return back()->with('error', "Data {$referensi} tidak perlu dikirim ulang.");
        }

        $berhasil = $catatan->sidik_diterima === $catatan->sidik;

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Data {$referensi} dikirim ulang ke Mappalus: ".($berhasil ? 'berhasil.' : "gagal. {$catatan->pesan}"),
        );

        return back()->with(
            $berhasil ? 'success' : 'error',
            $berhasil ? "Data {$referensi} diterima Mappalus." : "Data {$referensi} gagal terkirim: {$catatan->pesan}",
        );
    }

    private function pastikanAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Integrasi Mappalus hanya untuk super administrator.');
    }
}
