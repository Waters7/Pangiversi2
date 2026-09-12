<?php

namespace App\Http\Controllers;

use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif as DaftarNominatifModel;
use App\Models\DaftarRiil;
use App\Models\KategoriPembiayaan;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\EkspresiTanggal;
use App\Services\KertasCetak;
use App\Services\PenulisNominatifXlsx;
use App\Services\PenyusunNominatif;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LaporanController extends Controller
{
    public function __construct(
        private PenulisNominatifXlsx $nominatif,
        private EkspresiTanggal $tanggal,
    ) {}

    /**
     * Pusat Laporan — list semua pejadin yang disetujui dengan ringkasan anggaran.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status_keuangan');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');
        $pegawai = $request->input('pegawai');

        $query = Usulan::with('user', 'kegiatan', 'keuangan.rincianBiaya')
            ->whereIn('status', ['disetujui', 'selesai'])
            ->when($pegawai, fn ($q) => $q->where('id_user', $pegawai));

        // Search
        $query->when($search, function ($q) use ($search) {
            $q->where(function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhere('instansi', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
            });
        });

        // Filter status keuangan
        $query->when($status, function ($q) use ($status) {
            $q->whereHas('keuangan', fn ($q) => $q->where('status', $status));
        });

        // Periode keberangkatan
        $query->when($tahun, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->when($bulan, fn ($q) => $q->whereMonth('tanggal_mulai', $bulan));

        // Menurun agar bulan terbaru berada di atas, sekaligus menjaga
        // baris satu bulan tetap berkumpul di bawah judulnya.
        $usulan = $query->orderByDesc('tanggal_mulai')->paginate(10)->withQueryString();

        // Statistik keseluruhan
        $allApproved = Usulan::whereIn('status', ['disetujui', 'selesai'])->pluck('id');

        $stats = [
            'total_pejadin' => $allApproved->count(),
            'total_anggaran' => Keuangan::whereIn('id_usulan', $allApproved)->sum('total'),
            'total_terbayar' => Keuangan::whereIn('id_usulan', $allApproved)
                ->where('status', '!=', 'belum bayar')->sum('uang_muka')
                + Keuangan::whereIn('id_usulan', $allApproved)
                    ->where('status', 'lunas')->sum('sisa'),
            'lunas' => Keuangan::whereIn('id_usulan', $allApproved)->where('status', 'lunas')->count(),
            'sebagian' => Keuangan::whereIn('id_usulan', $allApproved)->where('status', 'bayar sebagian')->count(),
            'belum' => Keuangan::whereIn('id_usulan', $allApproved)->where('status', 'belum bayar')->count(),
        ];

        // Hanya pegawai yang benar-benar pernah melakukan perjalanan dinas
        // yang ditawarkan, agar daftarnya tidak memuat 193 nama sekaligus.
        $daftarPegawai = User::whereHas('usulan', fn ($q) => $q->whereIn('status', ['disetujui', 'selesai']))
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return view('laporan.index', [
            ...compact('usulan', 'stats', 'search', 'status', 'tahun', 'bulan', 'pegawai', 'daftarPegawai'),
            'tahunTersedia' => $this->tahunPerjadin(),
            'jumlahBulan' => $this->jumlahBulanPerjadin($tahun),
            'labelPeriode' => $this->labelPeriode($tahun, $bulan),
        ]);
    }

    /**
     * Detail laporan per pejadin.
     */
    public function show(Usulan $usulan)
    {
        $usulan->load(
            'user', 'kegiatan', 'dokumen',
            'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan',
            'peserta', 'daftarRiil.ppk', 'daftarRiil.peserta',
        );

        // Tiap peserta dipasangkan dengan daftar riilnya, supaya terlihat mana
        // yang sudah ditandatangani PPK dan mana yang masih tertahan.
        $riil = $usulan->peserta->map(fn (PesertaUsulan $peserta) => [
            'peserta' => $peserta,
            'daftar' => $usulan->daftarRiil->firstWhere('id_peserta', $peserta->id),
        ]);

        return view('laporan.show', compact('usulan', 'riil'));
    }

    /**
     * Daftar pengeluaran riil seluruh pelaksana, untuk Tim SDM. Hanya yang
     * sudah ditandatangani kedua belah pihak yang dapat diunduh — yang
     * lain masih dapat berubah.
     */
    /**
     * Rincian biaya yang sudah lengkap tanda tangannya.
     *
     * "Lengkap" berarti pelaksana sudah menyetujui angkanya dan PPK sudah
     * mengesahkan kedua dokumennya — rincian biaya maupun daftar riil.
     * Sejak itu dokumennya terkunci dan tidak berubah lagi, jadi inilah
     * berkas yang layak diarsipkan dan dicetak untuk lampiran. Daftar
     * nominatif tidak ditunggu: ia terbit per surat tugas dan dapat
     * menyusul belakangan, sedangkan rincian yang sudah disahkan PPK tidak
     * lagi bergantung padanya.
     */
    public function rincianLengkap(Request $request)
    {
        $cari = $request->input('cari');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        // Daftar riil yang nol tidak menunggu tanda tangan siapa pun; yang
        // menentukan lengkapnya adalah rincian biayanya.
        $semua = DaftarRiil::with('usulan.user', 'usulan.keuangan.rincianBiaya', 'peserta', 'ppk')
            ->whereNotNull('rincian_ditandatangani_at')
            ->where(fn ($q) => $q->whereNotNull('ditandatangani_at')->orWhere('total_riil', '<=', 0))
            ->whereHas('usulan')
            ->when($cari, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$cari}%")
                    ->orWhere('no_tugas', 'like', "%{$cari}%")
                    ->orWhereHas('user', fn ($p) => $p->where('nama', 'like', "%{$cari}%"))
            ))
            ->get()
            ->map(function (DaftarRiil $item) {
                // Waktu lengkapnya adalah tanda tangan terakhir yang dibubuhkan.
                $lengkap = $item->waktuDisahkanPpk();

                return [
                    'berkas' => $item,
                    'usulan' => $item->usulan,
                    'total' => $item->totalRincianBiaya(),
                    'tanggal' => $lengkap,
                ];
            })
            ->sortByDesc(fn (array $baris) => $baris['tanggal'])
            ->values();

        $daftar = $semua
            ->when($tahun, fn ($k) => $k->filter(fn (array $b) => $b['tanggal']?->year === (int) $tahun))
            ->when($bulan, fn ($k) => $k->filter(fn (array $b) => $b['tanggal']?->month === (int) $bulan))
            ->values();

        return view('laporan.rincian-lengkap', [
            'daftar' => $daftar->groupBy(fn (array $b) => $b['tanggal']?->translatedFormat('F Y') ?? 'Tanpa Tanggal'),
            'cari' => $cari,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $semua->pluck('tanggal')->filter()
                ->map(fn ($w) => $w->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $semua
                ->when($tahun, fn ($k) => $k->filter(fn (array $b) => $b['tanggal']?->year === (int) $tahun))
                ->pluck('tanggal')->filter()->countBy(fn ($w) => $w->month),
            'jumlahBerkas' => $daftar->count(),
            'totalNilai' => $daftar->sum(fn (array $b) => $b['total']),
        ]);
    }

    /**
     * Kelompok kerja daftar pengeluaran riil, sekaligus label tabnya.
     *
     * @var array<string, string>
     */
    private const TAHAP_RIIL = [
        'keuangan' => 'Diproses Tim Keuangan',
        'pelaksana' => 'Menunggu Pelaksana',
        'disanggah' => 'Disanggah',
        'ppk' => 'Menunggu PPK',
        'selesai' => 'Selesai',
    ];

    public function daftarRiil(Request $request)
    {
        $cari = $request->input('cari');
        $tahap = $request->input('tahap');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        $semua = DaftarRiil::with('usulan.user', 'peserta', 'ppk')
            ->when($cari, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$cari}%")
                    ->orWhere('no_tugas', 'like', "%{$cari}%")
            )->orWhereHas('peserta', fn ($p) => $p->where('nama', 'like', "%{$cari}%")))
            ->get()
            ->map(fn (DaftarRiil $item) => [
                'berkas' => $item,
                'tahap' => $this->tahapRiil($item),
                // Periodenya mengikuti keberangkatan, bukan tanggal berkasnya
                // dibuat: itulah bulan yang dicari saat menelusuri arsip.
                'tanggal' => $item->usulan?->tanggal_mulai
                    ? Carbon::parse($item->usulan->tanggal_mulai)
                    : null,
            ])
            ->sortByDesc(fn (array $baris) => $baris['tanggal'])
            ->values();

        $jumlah = collect(self::TAHAP_RIIL)
            ->map(fn (string $label, string $kunci) => $semua->where('tahap', $kunci)->count())
            ->all();

        $daftar = $semua
            ->when($tahap, fn ($koleksi) => $koleksi->where('tahap', $tahap))
            ->when($tahun, fn ($koleksi) => $koleksi->filter(
                fn (array $baris) => $baris['tanggal']?->year === (int) $tahun
            ))
            ->when($bulan, fn ($koleksi) => $koleksi->filter(
                fn (array $baris) => $baris['tanggal']?->month === (int) $bulan
            ))
            ->values()
            ->groupBy(fn (array $baris) => $baris['tanggal']?->translatedFormat('F Y') ?? 'Tanpa Tanggal');

        return view('laporan.daftar-riil', [
            'daftar' => $daftar,
            'cari' => $cari,
            'tahap' => $tahap,
            'labelTahap' => self::TAHAP_RIIL,
            'jumlah' => $jumlah,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $semua->pluck('tanggal')->filter()
                ->map(fn (Carbon $waktu) => $waktu->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $semua
                ->when($tahun, fn ($koleksi) => $koleksi->filter(
                    fn (array $baris) => $baris['tanggal']?->year === (int) $tahun
                ))
                ->pluck('tanggal')->filter()->countBy(fn (Carbon $waktu) => $waktu->month),
        ]);
    }

    /**
     * Tahap kerja satu daftar pengeluaran riil, dilihat dari siapa yang
     * sedang memegangnya.
     */
    private function tahapRiil(DaftarRiil $berkas): string
    {
        $jalur = $berkas->jalur();

        return match (true) {
            $jalur->sudahDitandatangani() => 'selesai',
            $jalur->sedangDisanggah() => 'disanggah',
            $jalur->sudahDisetujui(), $jalur->sanggahKedaluwarsa() => 'ppk',
            $jalur->masaSanggahBerjalan() => 'pelaksana',
            default => 'keuangan',
        };
    }

    /**
     * Daftar nominatif per surat tugas yang sudah dikirim PPK.
     */
    /**
     * Saringan status daftar nominatif beserta labelnya.
     *
     * @var array<string, string>
     */
    private const STATUS_NOMINATIF = [
        'menunggu' => 'Menunggu Tanda Tangan PPK',
        'ditandatangani' => 'Ditandatangani PPK',
        'diterima' => 'Diterima Tim Keuangan',
    ];

    /**
     * Seluruh daftar nominatif yang sudah terbit — bukan hanya yang sudah
     * diterima — supaya tim keuangan melihat mana yang masih menunggu PPK,
     * beserta siapa saja pelaksana yang sudah menandatangani berkasnya.
     */
    public function nominatif(Request $request, PenyusunNominatif $penyusun)
    {
        $cari = $request->input('cari');

        // Periodenya mengikuti tanggal daftar diterima tim keuangan — itulah
        // tanggal yang menentukan pembukuannya; yang belum diterima memakai
        // tanggal surat tugasnya supaya tetap berada di bulan yang wajar.
        $tanggal = fn (DaftarNominatifModel $item) => $item->dikirim_at ?? $item->tanggal_tugas ?? $item->created_at;

        $semua = DaftarNominatifModel::with('ppk', 'kategoriPembiayaan', 'akunPembiayaan')
            ->when($cari, fn ($q) => $q->where('no_tugas', 'like', "%{$cari}%"))
            ->get()
            ->sortByDesc($tanggal)
            ->values();

        // Disaring per akun bila diminta, supaya terlihat berapa yang keluar
        // dari tiap mata anggaran.
        $akun = $request->input('akun');

        $status = array_key_exists((string) $request->input('status'), self::STATUS_NOMINATIF)
            ? $request->input('status')
            : null;

        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        $berakun = $semua->when($akun !== null && $akun !== '', fn ($koleksi) => $koleksi->where(
            'id_akun_pembiayaan',
            $akun === 'belum' ? null : (int) $akun,
        ));

        $cocokStatus = fn (DaftarNominatifModel $item, string $kunci) => match ($kunci) {
            'diterima' => $item->sudahDikirim(),
            'ditandatangani' => $item->sudahDitandatangani() && ! $item->sudahDikirim(),
            'menunggu' => ! $item->sudahDitandatangani(),
        };

        $tampil = $berakun
            ->when($status, fn ($koleksi) => $koleksi->filter(
                fn (DaftarNominatifModel $item) => $cocokStatus($item, $status)
            ))
            ->when($tahun, fn ($koleksi) => $koleksi->filter(
                fn (DaftarNominatifModel $item) => $tanggal($item)?->year === (int) $tahun
            ))
            ->when($bulan, fn ($koleksi) => $koleksi->filter(
                fn (DaftarNominatifModel $item) => $tanggal($item)?->month === (int) $bulan
            ));

        // Barisnya dimuat sekali untuk seluruh surat tugas yang tampil, bukan
        // satu rangkaian kueri per daftar.
        $baris = $penyusun->barisBanyak($tampil->pluck('no_tugas'));
        $tandaTangan = $penyusun->tandaTanganBanyak($tampil->pluck('no_tugas'));

        $daftar = $tampil
            ->map(fn (DaftarNominatifModel $item) => [
                'nominatif' => $item,
                'baris' => $baris->get($item->no_tugas) ?? collect(),
                'tandaTangan' => $tandaTangan->get($item->no_tugas) ?? collect(),
                'periode' => $tanggal($item)?->translatedFormat('F Y') ?? 'Tanpa Tanggal',
            ])
            ->values()
            ->groupBy('periode');

        return view('laporan.nominatif', [
            'daftar' => $daftar,
            'akun' => $akun,
            'cari' => $cari,
            'status' => $status,
            'pilihanStatus' => self::STATUS_NOMINATIF,
            'jumlahStatus' => collect(self::STATUS_NOMINATIF)->map(
                fn (string $label, string $kunci) => $berakun->filter(
                    fn (DaftarNominatifModel $item) => $cocokStatus($item, $kunci)
                )->count()
            ),
            'tahun' => $tahun,
            'bulan' => $bulan,
            // Hanya tahun yang benar-benar berisi yang ditawarkan, dan
            // hitungan bulannya mengikuti akun yang sedang dilihat.
            'tahunTersedia' => $berakun->map($tanggal)->filter()
                ->map(fn ($waktu) => $waktu->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $berakun
                ->when($tahun, fn ($koleksi) => $koleksi->filter(
                    fn (DaftarNominatifModel $item) => $tanggal($item)?->year === (int) $tahun
                ))
                ->map($tanggal)->filter()->countBy(fn ($waktu) => $waktu->month),
            'pilihanAkun' => AkunPembiayaan::pilihan(),
            'pilihanKategori' => KategoriPembiayaan::pilihan(),
            'jumlahBelumBerakun' => $semua->whereNull('id_akun_pembiayaan')->count(),
        ]);
    }

    /**
     * Cetak daftar nominatif mengikuti format lembar rekap keuangan.
     */
    public function cetakNominatif(
        DaftarNominatifModel $nominatif,
        PenyusunNominatif $penyusun,
        QrCodeService $qrCode,
    ): Response {
        $baris = $penyusun->baris($nominatif->no_tugas);

        $pdf = Pdf::loadView('laporan.cetak-nominatif', [
            'nominatif' => $nominatif->load('ppk', 'kategoriPembiayaan', 'akunPembiayaan'),
            'baris' => $baris,
            'total' => $penyusun->total($baris),
            'ppk' => $nominatif->ppk ?? User::where('role', User::ROLE_PPK)->first(),

            // QR hanya dicetak setelah PPK benar-benar membubuhkan tanda
            // tangannya; sebelum itu ruangnya dibiarkan kosong.
            'qr' => $nominatif->urlVerifikasi()
                ? $qrCode->dataUri($nominatif->urlVerifikasi(), 180)
                : null,
        ])->setPaper(KertasCetak::UKURAN, KertasCetak::MENDATAR);

        return $pdf->download('Daftar-Nominatif_'.Str::slug($nominatif->no_tugas).'.pdf');
    }

    /**
     * Tetapkan pembebanan sebuah daftar nominatif.
     *
     * Dua hal yang berbeda: kategori menyatakan sumber dananya (RM, BLU, LN),
     * akun menyatakan mata anggaran yang dibebani. Satu sumber dana dapat
     * membebani beberapa akun, jadi keduanya dipilih terpisah.
     */
    public function tetapkanAkun(Request $request, DaftarNominatifModel $nominatif): RedirectResponse
    {
        $validated = $request->validate([
            'id_kategori_pembiayaan' => ['nullable', 'exists:kategori_pembiayaan,id'],
            'id_akun_pembiayaan' => ['nullable', 'exists:akun_pembiayaan,id'],
        ]);

        $nominatif->update($validated);

        return back()->with('success', "Pembebanan daftar nominatif {$nominatif->no_tugas} diperbarui.");
    }

    /**
     * Nama periode yang sedang dipilih, dipakai layar maupun judul
     * berkas ekspornya supaya keduanya tidak pernah berbeda.
     */
    private function labelPeriode(?string $tahun, ?string $bulan): string
    {
        return match (true) {
            $tahun && $bulan => Carbon::create((int) $tahun, (int) $bulan, 1)->translatedFormat('F Y'),
            (bool) $bulan => Carbon::create(null, (int) $bulan, 1)->translatedFormat('F'),
            (bool) $tahun => 'Tahun '.$tahun,
            default => 'Seluruh Periode',
        };
    }

    /**
     * Tahun yang benar-benar punya perjalanan dinas, untuk mengisi tombol
     * tahun pada saringan periode.
     *
     * @return Collection<int, int>
     */
    private function tahunPerjadin(): Collection
    {
        return Usulan::query()
            ->whereIn('status', ['disetujui', 'selesai'])
            ->whereNotNull('tanggal_mulai')
            ->selectRaw($this->tanggal->tahun('tanggal_mulai').' as tahun')
            ->distinct()
            ->pluck('tahun')
            ->filter()
            ->map(fn ($nilai) => (int) $nilai)
            ->sortDesc()
            ->values();
    }

    /**
     * Jumlah perjalanan per bulan pada tahun yang sedang dilihat.
     *
     * @return Collection<int, int>
     */
    private function jumlahBulanPerjadin(?string $tahun): Collection
    {
        return Usulan::query()
            ->whereIn('status', ['disetujui', 'selesai'])
            ->whereNotNull('tanggal_mulai')
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->selectRaw($this->tanggal->bulan('tanggal_mulai').' as bulan, count(*) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan')
            ->mapWithKeys(fn ($jumlah, $kunci) => [(int) $kunci => (int) $jumlah]);
    }

    /**
     * Tahun dan bulan yang diminta untuk ekspor.
     *
     * Bentuk lama 'bulan=Y-m' masih diterima agar pranala dan pintasan yang
     * sudah tersimpan tidak mendadak mengunduh seluruh periode.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function periodeEkspor(Request $request): array
    {
        $bulan = $request->input('bulan');

        if (is_string($bulan) && preg_match('/^(\d{4})-(\d{2})$/', $bulan, $cocok) === 1) {
            return [$cocok[1], ltrim($cocok[2], '0')];
        }

        return [$request->input('tahun'), $bulan];
    }

    public function exportExcel(Request $request): Response
    {
        // Bulan lama ditulis 'Y-m'; sekarang tahun dan bulannya dipilih
        // terpisah. Keduanya diterima agar pranala lama tetap berjalan.
        [$tahun, $bulan] = $this->periodeEkspor($request);

        $pegawai = $request->input('pegawai')
            ? User::find($request->input('pegawai'))
            : null;

        $usulan = Usulan::with('user', 'kegiatan', 'keuangan.rincianBiaya')
            ->whereIn('status', ['disetujui', 'selesai'])
            ->when($tahun, fn ($query) => $query->whereYear('tanggal_mulai', $tahun))
            ->when($bulan, fn ($query) => $query->whereMonth('tanggal_mulai', $bulan))
            ->when($pegawai, fn ($query) => $query->where('id_user', $pegawai->id))
            ->when($request->input('status_keuangan'), fn ($query, $status) => $query
                ->whereHas('keuangan', fn ($q) => $q->where('status', $status)))
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                });
            })
            ->orderBy('tanggal_mulai')
            ->get();

        // Tanggal 1 ditulis eksplisit: createFromFormat('Y-m') mengambil hari
        // dari tanggal hari ini, sehingga tanggal 29–31 meluber ke bulan
        // berikutnya saat bulan tujuannya lebih pendek.
        $periode = $this->labelPeriode($tahun, $bulan);

        // Rekap per pegawai memakai judul dan nama berkas yang menyebut orangnya,
        // sehingga berkas untuk beberapa pegawai tidak saling tertukar.
        $judul = $pegawai ? $pegawai->nama.' — '.$periode : $periode;

        $berkas = $this->nominatif->susun($usulan, $judul, $pegawai)->keString();

        $nama = 'Daftar-Nominatif-'
            .($pegawai ? Str::slug($pegawai->nama).'-' : '')
            .str_replace(' ', '-', $periode).'.xlsx';

        return response($berkas, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Content-Length' => (string) strlen($berkas),
        ]);
    }
}
