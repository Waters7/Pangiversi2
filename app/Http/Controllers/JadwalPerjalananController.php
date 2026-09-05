<?php

namespace App\Http\Controllers;

use App\Enums\StatusUsulan;
use App\Models\PesertaUsulan;
use App\Services\EkspresiTanggal;
use App\Services\RekapJadwal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Daftar pegawai yang akan melaksanakan perjalanan dinas, mencakup yang
 * jadwalnya sudah pasti (disetujui) maupun yang masih dalam pengajuan.
 *
 * Halaman ini memuat identitas peserta dan waktu keberangkatan, tanpa
 * nominal biaya maupun berkas pertanggungjawaban.
 */
class JadwalPerjalananController extends Controller
{
    public function __construct(
        private RekapJadwal $rekap,
        private EkspresiTanggal $tanggal,
    ) {}

    public function index(Request $request): View
    {
        $peserta = $this->cari($request);

        $sudahFix = $peserta->filter(
            fn (PesertaUsulan $item) => StatusUsulan::dari($item->usulan?->status)->sudahFix()
        );

        // Kartu ringkasan menghitung bulan berjalan saja, dan ikut berpindah
        // sendiri saat bulannya berganti — now() dibaca ulang tiap permintaan.
        $bulanIni = $this->pesertaBulanIni($request);
        $fixBulanIni = $bulanIni->filter(
            fn (PesertaUsulan $item) => StatusUsulan::dari($item->usulan?->status)->sudahFix()
        );

        return view('jadwal-perjalanan.index', [
            'peserta' => $peserta,
            'labelBulanIni' => now()->translatedFormat('F Y'),
            'totalPegawai' => $bulanIni->pluck('nama')->unique()->count(),
            'totalPerjalanan' => $bulanIni->pluck('id_usulan')->unique()->count(),
            'totalFix' => $fixBulanIni->count(),
            'totalSementara' => $bulanIni->count() - $fixBulanIni->count(),
            'search' => $request->input('search'),
            'periode' => $request->input('periode', 'mendatang'),
            'kepastian' => $request->input('kepastian', 'semua'),
            'bulan' => $request->input('bulan'),
            'bulanTersedia' => $this->bulanTersedia(),
        ]);
    }

    /**
     * Unduh rekap bulanan sebagai berkas Excel.
     *
     * Bila bulannya tidak dipilih, rekapnya mengikuti bulan berjalan supaya
     * tombolnya selalu menghasilkan berkas yang bermakna.
     */
    public function ekspor(Request $request): Response
    {
        $bulan = $request->input('bulan') ?: now()->format('Y-m');
        $request->merge(['bulan' => $bulan, 'periode' => 'semua']);

        $peserta = $this->cari($request);
        // Tanggal 1 ditulis eksplisit agar bulan pendek seperti Februari tidak
        // meluber ke bulan berikutnya saat hari ini tanggal 29–31.
        $judul = Carbon::parse($bulan.'-01')->translatedFormat('F Y');

        $berkas = $this->rekap->susun($peserta, $judul)->keString();
        $nama = 'Rekap-Jadwal-Perjadin-'.str_replace(' ', '-', $judul).'.xlsx';

        return response($berkas, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Content-Length' => (string) strlen($berkas),
        ]);
    }

    /**
     * Peserta yang berangkat pada bulan berjalan. Saringan kepastian dan
     * pencarian tetap dihormati — hanya periodenya yang dipaksa ke bulan
     * ini, karena kartunya memang meringkas bulan yang sedang berjalan.
     *
     * @return Collection<int, PesertaUsulan>
     */
    private function pesertaBulanIni(Request $request): Collection
    {
        return $this->cari(new Request([
            'bulan' => now()->format('Y-m'),
            'periode' => 'semua',
            'kepastian' => $request->input('kepastian', 'semua'),
            'search' => $request->input('search'),
        ]));
    }

    private function cari(Request $request): Collection
    {
        $search = $request->input('search');
        $periode = $request->input('periode', 'mendatang');
        $kepastian = $request->input('kepastian', 'semua');
        $bulan = $request->input('bulan');

        $statusTampil = match ($kepastian) {
            'fix' => [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value],
            'sementara' => StatusUsulan::nilaiMenunggu(),
            default => [
                StatusUsulan::Disetujui->value,
                StatusUsulan::Selesai->value,
                ...StatusUsulan::nilaiMenunggu(),
            ],
        };

        return PesertaUsulan::query()
            ->select(['id', 'id_usulan', 'id_user', 'nama', 'nip', 'jabatan', 'peran'])
            ->with([
                'user:id,nama,id_unit',
                'user.unit:id,nama',
                'usulan:id,no_usulan,lokasi,instansi,tanggal_mulai,tanggal_selesai,status',
            ])
            ->whereHas('usulan', function ($query) use ($statusTampil, $periode, $bulan): void {
                $query->whereIn('status', $statusTampil);

                if ($bulan) {
                    $query->whereRaw($this->tanggal->tahunBulan('tanggal_mulai').' = ?', [$bulan]);
                } elseif ($periode === 'mendatang') {
                    $query->whereDate('tanggal_selesai', '>=', today());
                } elseif ($periode === 'berlalu') {
                    $query->whereDate('tanggal_selesai', '<', today());
                }
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhereHas('usulan', fn ($q) => $q->where('lokasi', 'like', "%{$search}%"));
                });
            })
            ->get()
            ->sortBy(fn (PesertaUsulan $item) => $item->usulan?->tanggal_mulai)
            ->values();
    }

    /**
     * Bulan yang benar-benar punya keberangkatan, untuk mengisi pilihan ekspor.
     *
     * @return Collection<string, string>
     */
    private function bulanTersedia(): Collection
    {
        return PesertaUsulan::query()
            ->join('usulan', 'usulan.id', '=', 'peserta_usulan.id_usulan')
            ->selectRaw('DISTINCT '.$this->tanggal->tahunBulan('usulan.tanggal_mulai').' as bulan')
            ->whereNotNull('usulan.tanggal_mulai')
            ->orderByDesc('bulan')
            ->pluck('bulan')
            ->filter()
            ->mapWithKeys(fn (string $bulan) => [
                $bulan => Carbon::parse($bulan.'-01')->translatedFormat('F Y'),
            ]);
    }
}
