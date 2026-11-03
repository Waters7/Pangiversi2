<?php

namespace App\Http\Controllers;

use App\Models\AkunPembiayaan;
use App\Models\AuditLog;
use App\Models\DaftarNominatif;
use App\Models\KategoriPembiayaan;
use App\Models\TahunAnggaran;
use App\Services\AuditService;
use App\Services\PembebananNominatif;
use App\Services\PenyusunNominatif;
use App\Services\RingkasanEksekutif;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu Pembebanan Anggaran: menetapkan sumber dana, akun pembiayaan, dan
 * tim kerja tiap daftar nominatif — yang masih menunggu tanda tangan PPK,
 * yang sudah ditandatangani, maupun yang sudah diterima tim keuangan —
 * berdampingan dengan pagu dan realisasi tiap tim kerja.
 */
class PembebananAnggaranController extends Controller
{
    /** @var array<string, string> */
    private const TAHAP = [
        'menunggu' => 'Menunggu Tanda Tangan PPK',
        'ditandatangani' => 'Ditandatangani PPK',
        'diterima' => 'Diterima Tim Keuangan',
    ];

    /** @var array<string, string> */
    private const PEMBEBANAN = [
        'belum' => 'Belum lengkap',
        'lengkap' => 'Sudah lengkap',
    ];

    public function __construct(private AuditService $audit) {}

    public function index(
        Request $request,
        PenyusunNominatif $penyusun,
        PembebananNominatif $pembebanan,
        RingkasanEksekutif $ringkasan,
    ): View {
        // Daftar yang sudah siap terbit ikut tampil walau PPK belum membuka antreannya.
        $penyusun->terbitkanYangSiap();

        $tahap = array_key_exists((string) $request->input('tahap'), self::TAHAP) ? $request->input('tahap') : null;
        $saringPembebanan = array_key_exists((string) $request->input('pembebanan'), self::PEMBEBANAN) ? $request->input('pembebanan') : null;
        $cari = trim((string) $request->input('cari'));

        $dasar = fn () => DaftarNominatif::query()
            ->when($cari !== '', fn (Builder $q) => $q->where('no_tugas', 'like', "%{$cari}%"))
            ->when($saringPembebanan === 'belum', fn (Builder $q) => $this->belumLengkap($q))
            ->when($saringPembebanan === 'lengkap', fn (Builder $q) => $q->whereNotNull('id_akun_pembiayaan')->whereNotNull('id_tim_kerja'));

        $daftar = $dasar()
            ->with('ppk', 'kategoriPembiayaan', 'akunPembiayaan', 'timKerja')
            ->when($tahap, fn (Builder $q) => $this->saringTahap($q, $tahap))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $tahunAnggaran = TahunAnggaran::find($request->input('ta')) ?? TahunAnggaran::aktif();

        return view('pembebanan-anggaran.index', [
            'daftar' => $daftar,
            'baris' => $penyusun->barisBanyak($daftar->getCollection()->pluck('no_tugas')),
            'alokasi' => $pembebanan->untukDaftar($daftar->getCollection()),
            'tahap' => $tahap,
            'pilihanTahap' => self::TAHAP,
            'jumlahTahap' => collect(self::TAHAP)->map(fn (string $label, string $kunci) => $this->saringTahap($dasar(), $kunci)->count()),
            'saringPembebanan' => $saringPembebanan,
            'pilihanPembebanan' => self::PEMBEBANAN,
            'cari' => $cari,
            'pilihanAkun' => AkunPembiayaan::pilihan(),
            'pilihanKategori' => KategoriPembiayaan::pilihan(),
            'tahunAnggaran' => $tahunAnggaran,
            'pilihanTahun' => TahunAnggaran::orderByDesc('tahun')->get(),
            'anggaranTim' => $tahunAnggaran ? $ringkasan->anggaranPerTimKerja($tahunAnggaran->tahun) : collect(),
            'jumlahBelumLengkap' => $this->belumLengkap(DaftarNominatif::query())->count(),
        ]);
    }

    public function update(Request $request, DaftarNominatif $nominatif, PembebananNominatif $pembebanan): RedirectResponse
    {
        $pembebanan->tetapkan($request, $nominatif);
        $nominatif->load('kategoriPembiayaan', 'akunPembiayaan', 'timKerja');

        $ringkas = collect([
            $nominatif->kategoriPembiayaan?->kode,
            $nominatif->akunPembiayaan?->kode,
            $nominatif->timKerja?->nama,
        ])->filter()->implode(' · ') ?: 'dikosongkan';

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Pembebanan daftar nominatif {$nominatif->no_tugas}: {$ringkas}.",
            ['objek' => 'pembebanan-nominatif'],
        );

        return back()->with('success', "Pembebanan daftar nominatif {$nominatif->no_tugas} diperbarui: {$ringkas}.");
    }

    /**
     * Tahap daftar menurut tanda tangan PPK dan pengirimannya, sejalan
     * dengan List Daftar Nominatif.
     *
     * @param  Builder<DaftarNominatif>  $query
     * @return Builder<DaftarNominatif>
     */
    private function saringTahap(Builder $query, string $tahap): Builder
    {
        return match ($tahap) {
            'menunggu' => $query->whereNull('ditandatangani_at'),
            'ditandatangani' => $query->whereNotNull('ditandatangani_at')->whereNull('dikirim_at'),
            'diterima' => $query->whereNotNull('dikirim_at'),
        };
    }

    /**
     * Belum lengkap: akun atau tim kerjanya belum ditetapkan.
     *
     * @param  Builder<DaftarNominatif>  $query
     * @return Builder<DaftarNominatif>
     */
    private function belumLengkap(Builder $query): Builder
    {
        return $query->where(fn (Builder $w) => $w->whereNull('id_akun_pembiayaan')->orWhereNull('id_tim_kerja'));
    }
}
