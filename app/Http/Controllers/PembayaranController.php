<?php

namespace App\Http\Controllers;

use App\Enums\Kemampuan;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\Notifikasi;
use App\Models\RiwayatPembayaran;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\EkspresiTanggal;
use App\Services\NotifikasiService;
use App\Services\PenagihDokumen;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Menu kerja bendahara: memantau perjalanan dinas menurut tahap
 * pembayarannya, dari yang baru disetujui sampai yang sudah lunas.
 */
class PembayaranController extends Controller
{
    /**
     * Tahap yang dapat dipilih, sekaligus label tabnya.
     *
     * @var array<string, string>
     */
    private const TAHAP = [
        'disetujui' => 'Menunggu Pembayaran',
        'berjalan' => 'Sedang Berjalan',
        'uang-muka' => 'Uang Muka',
        'lunas' => 'Lunas 100%',
    ];

    /**
     * Saringan status pembayaran pada daftar tahap.
     *
     * @var array<string, string>
     */
    private const STATUS = [
        'belum' => 'Belum dibayar',
        'sebagian' => 'Uang muka dibayar',
        'lunas' => 'Lunas',
    ];

    /**
     * Pilihan urutan daftar tahap; bawaannya tanggal berangkat terlama
     * supaya baris satu bulan berkumpul di bawah judul bulannya.
     *
     * @var array<string, string>
     */
    private const URUTAN = [
        'berangkat-terlama' => 'Tanggal berangkat terlama',
        'berangkat-terbaru' => 'Tanggal berangkat terbaru',
        'nama-az' => 'Nama pelaksana A–Z',
        'nama-za' => 'Nama pelaksana Z–A',
        'anggaran-besar' => 'Anggaran terbesar',
        'anggaran-kecil' => 'Anggaran terkecil',
        'status' => 'Status pembayaran',
    ];

    /**
     * Jenis tindakan pada jurnal pembayaran, sekaligus label tabnya.
     *
     * @var array<string, string>
     */
    private const JENIS = [
        RiwayatPembayaran::JENIS_UANG_MUKA => 'Uang Muka',
        RiwayatPembayaran::JENIS_PELUNASAN => 'Pelunasan',
        RiwayatPembayaran::JENIS_TRANSPORT_LOKAL => 'Transport Lokal',
        RiwayatPembayaran::JENIS_BATAL_UANG_MUKA => 'Pembatalan Uang Muka',
        RiwayatPembayaran::JENIS_BATAL_PELUNASAN => 'Pembatalan Pelunasan',
    ];

    public function __construct(
        private PenagihDokumen $penagih,
        private EkspresiTanggal $tanggal,
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    public function index(Request $request): View
    {
        $tahap = array_key_exists($request->input('tahap'), self::TAHAP)
            ? $request->input('tahap')
            : 'disetujui';

        // Periode dibaca dari tanggal berangkat: bendahara membayar menurut
        // bulan perjalanannya, bukan bulan usulannya dibuat.
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        $saring = [
            'status' => array_key_exists((string) $request->input('status'), self::STATUS) ? $request->input('status') : null,
            'pelaksana' => trim((string) $request->input('pelaksana')),
            'perjalanan' => trim((string) $request->input('perjalanan')),
            'anggaran_min' => is_numeric($request->input('anggaran_min')) ? (float) $request->input('anggaran_min') : null,
            'anggaran_maks' => is_numeric($request->input('anggaran_maks')) ? (float) $request->input('anggaran_maks') : null,
        ];
        $urut = array_key_exists((string) $request->input('urut'), self::URUTAN) ? $request->input('urut') : 'berangkat-terlama';

        $usulan = $this->daftar($tahap, $request->input('search'), $tahun, $bulan, $saring, $urut);

        // Kelengkapan berkas dihitung di sini agar Blade tidak memeriksanya
        // ulang untuk setiap baris.
        $usulan->getCollection()->each(function (Usulan $item): void {
            $item->berkas_kurang = $this->penagih->berkasKurang($item);
            $item->berkas_lengkap = $item->berkas_kurang === [];
        });

        return view('pembayaran.index', [
            'tahap' => $tahap,
            'daftarTahap' => self::TAHAP,
            'jumlah' => $this->jumlahPerTahap(),
            'nilai' => $this->nilaiPembayaran(),
            'usulan' => $usulan,
            'search' => $request->input('search'),
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $this->tahunTersedia($tahap),
            'jumlahBulan' => $this->jumlahBulan($tahap, $tahun),
            'saring' => $saring,
            'pilihanStatus' => self::STATUS,
            'urut' => $urut,
            'pilihanUrut' => self::URUTAN,
        ]);
    }

    /**
     * Riwayat pembayaran yang dilakukan bendahara, dikelompokkan per bulan.
     *
     * Halaman ini menjawab pertanyaan yang tidak terjawab daftar tahap:
     * apa saja yang sudah saya bayarkan, kapan, dan berapa nilainya.
     */
    public function riwayat(Request $request): View
    {
        $search = $request->input('search');
        $jenis = $request->input('jenis');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        $semua = RiwayatPembayaran::with('usulan.user', 'pencatat')
            ->when($search, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($p) => $p->where('nama', 'like', "%{$search}%"))
            ))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        $jumlahJenis = collect(self::JENIS)
            ->map(fn (string $label, string $kunci) => $semua->where('jenis', $kunci)->count())
            ->all();

        $riwayat = $semua
            ->when($jenis, fn ($koleksi) => $koleksi->where('jenis', $jenis))
            ->when($tahun, fn ($koleksi) => $koleksi->filter(
                fn (RiwayatPembayaran $baris) => $baris->tanggal?->year === (int) $tahun
            ))
            ->when($bulan, fn ($koleksi) => $koleksi->filter(
                fn (RiwayatPembayaran $baris) => $baris->tanggal?->month === (int) $bulan
            ))
            ->values();

        return view('pembayaran.riwayat', [
            'riwayat' => $riwayat->groupBy(fn (RiwayatPembayaran $baris) => $baris->tanggal?->translatedFormat('F Y') ?? 'Tanpa Tanggal'),
            'search' => $search,
            'jenis' => $jenis,
            'daftarJenis' => self::JENIS,
            'jumlahJenis' => $jumlahJenis,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $semua->pluck('tanggal')->filter()
                ->map(fn ($waktu) => $waktu->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $semua
                ->when($tahun, fn ($koleksi) => $koleksi->filter(
                    fn (RiwayatPembayaran $baris) => $baris->tanggal?->year === (int) $tahun
                ))
                ->pluck('tanggal')->filter()->countBy(fn ($waktu) => $waktu->month),
            // Arus kas bersih: pembatalan mengurangi, bukan menambah.
            'totalTerbayar' => $riwayat->sum(fn (RiwayatPembayaran $baris) => $baris->nilaiArus()),
        ]);
    }

    /**
     * Penggantian transport lokal, dibayarkan dan dicatat sendiri.
     *
     * Nominalnya berasal dari Daftar Pengeluaran Riil — di luar rincian
     * biaya — dan kerap ditransfer terpisah dari pelunasan. Yang belum
     * dibayarkan di sini tetap ikut pada pelunasan, jadi tidak ada berkas
     * yang tertinggal.
     */
    public function transportLokal(Request $request): View
    {
        $search = $request->input('search');
        $tahap = $request->input('tahap');
        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        $semua = DaftarRiil::with('usulan.user', 'peserta', 'ppk', 'pembayar')
            ->whereNotNull('ditandatangani_at')
            ->where('total_riil', '>', 0)
            ->when($search, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($p) => $p->where('nama', 'like', "%{$search}%"))
            ))
            ->get()
            ->map(fn (DaftarRiil $item) => [
                'berkas' => $item,
                'tahap' => $item->sudahDibayar() ? 'terbayar' : 'menunggu',
                // Periodenya mengikuti keberangkatan sampai ia terbayar,
                // lalu berpindah ke bulan pembayarannya.
                'tanggal' => $item->dibayar_at
                    ?? ($item->usulan?->tanggal_mulai ? Carbon::parse($item->usulan->tanggal_mulai) : null),
            ])
            ->sortByDesc(fn (array $baris) => $baris['tanggal'])
            ->values();

        $jumlah = [
            'menunggu' => $semua->where('tahap', 'menunggu')->count(),
            'terbayar' => $semua->where('tahap', 'terbayar')->count(),
        ];

        $tampil = $semua
            ->when($tahap, fn ($k) => $k->where('tahap', $tahap))
            ->when($tahun, fn ($k) => $k->filter(fn (array $b) => $b['tanggal']?->year === (int) $tahun))
            ->when($bulan, fn ($k) => $k->filter(fn (array $b) => $b['tanggal']?->month === (int) $bulan))
            ->values();

        return view('pembayaran.transport-lokal', [
            'daftar' => $tampil->groupBy(fn (array $b) => $b['tanggal']?->translatedFormat('F Y') ?? 'Tanpa Tanggal'),
            'search' => $search,
            'tahap' => $tahap,
            'jumlah' => $jumlah,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $semua->pluck('tanggal')->filter()
                ->map(fn ($w) => $w->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $semua
                ->when($tahun, fn ($k) => $k->filter(fn (array $b) => $b['tanggal']?->year === (int) $tahun))
                ->pluck('tanggal')->filter()->countBy(fn ($w) => $w->month),
            'totalMenunggu' => $semua->where('tahap', 'menunggu')->sum(fn (array $b) => $b['berkas']->total_riil),
            'totalTerbayar' => $tampil->where('tahap', 'terbayar')->sum(fn (array $b) => $b['berkas']->total_riil),
        ]);
    }

    /**
     * Batalkan catatan penggantian transport lokal yang keliru.
     *
     * Seperti pembatalan uang muka dan pelunasan: wajib beralasan, dicatat
     * sebagai baris jurnal tersendiri, dan bukti lamanya tidak dihapus.
     */
    public function batalBayarTransport(Request $request, DaftarRiil $daftar): RedirectResponse
    {
        abort_unless(
            $request->user()->punyaKemampuan(Kemampuan::MencatatPembayaran),
            403,
            'Pencatatan pembayaran dikerjakan bendahara.'
        );

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'alasan.required' => 'Jelaskan kenapa pembayaran ini dibatalkan.',
            'alasan.min' => 'Uraikan alasannya sedikit lebih rinci.',
        ]);

        if (! $daftar->sudahDibayar()) {
            return back()->with('error', 'Penggantian transport lokal ini belum pernah dicatat.');
        }

        $usulan = $daftar->usulan;
        $nominal = (float) $daftar->total_riil;
        $tanggal = $daftar->dibayar_at->toDateString();
        $teksNominal = 'Rp '.number_format($nominal, 0, ',', '.');

        $daftar->batalkanBayarTransport();

        if ($usulan?->keuangan) {
            RiwayatPembayaran::catat(
                $usulan->keuangan,
                RiwayatPembayaran::JENIS_BATAL_TRANSPORT_LOKAL,
                $nominal,
                $tanggal,
                $request->user(),
                null,
                $validated['alasan'],
            );
        }

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            "Penggantian transport lokal {$teksNominal} pada usulan {$usulan?->no_usulan} dibatalkan.",
            ['usulan' => $usulan, 'catatan' => $validated['alasan']],
        );

        if ($daftar->peserta?->user) {
            $this->notifikasi->kirim(
                $daftar->peserta->user,
                'Penggantian transport lokal dibatalkan',
                "Catatan penggantian transport lokal {$teksNominal} dibatalkan bendahara: {$validated['alasan']}",
                ['usulan' => $usulan, 'tipe' => Notifikasi::TIPE_PERINGATAN],
            );
        }

        return back()->with('success', "Penggantian transport lokal {$teksNominal} dibatalkan dan dicatat pada riwayat pembayaran.");
    }

    /**
     * Catat penggantian transport lokal seorang pelaksana.
     */
    public function bayarTransport(Request $request, DaftarRiil $daftar): RedirectResponse
    {
        abort_unless(
            $request->user()->punyaKemampuan(Kemampuan::MencatatPembayaran),
            403,
            'Pencatatan pembayaran dikerjakan bendahara.'
        );

        abort_unless(
            $daftar->sudah_ditandatangani,
            403,
            'Daftar pengeluaran riil ini belum ditandatangani PPK.'
        );

        if ($daftar->sudahDibayar()) {
            abort(422, 'Penggantian transport lokal ini sudah tercatat pada '
                .$daftar->dibayar_at->translatedFormat('d F Y').'.');
        }

        $validated = $request->validate([
            'tanggal_bayar' => ['required', 'date'],
            'bukti_bayar' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'tanggal_bayar.required' => 'Isi tanggal transfernya.',
            'bukti_bayar.required' => 'Unggah bukti transfernya.',
        ]);

        $bukti = $request->file('bukti_bayar')->store('keuangan/transport-lokal', 'public');
        $daftar->bayarTransport($validated['tanggal_bayar'], $bukti, $request->user());

        $usulan = $daftar->usulan;
        $nominal = 'Rp '.number_format($daftar->total_riil, 0, ',', '.');

        if ($usulan?->keuangan) {
            RiwayatPembayaran::catat(
                $usulan->keuangan,
                RiwayatPembayaran::JENIS_TRANSPORT_LOKAL,
                (float) $daftar->total_riil,
                $validated['tanggal_bayar'],
                $request->user(),
                $bukti,
                'Penggantian transport lokal '.($daftar->peserta?->nama ?? ''),
            );
        }

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            "Penggantian transport lokal {$nominal} pada usulan {$usulan?->no_usulan} dibayarkan.",
            ['usulan' => $usulan],
        );

        if ($daftar->peserta?->user) {
            $this->notifikasi->kirim(
                $daftar->peserta->user,
                'Penggantian transport lokal dibayarkan',
                "Penggantian transport lokal {$nominal} sudah ditransfer.",
                ['usulan' => $usulan, 'tipe' => Notifikasi::TIPE_SUKSES],
            );
        }

        return back()->with('success', "Penggantian transport lokal {$nominal} tercatat.");
    }

    /**
     * @return LengthAwarePaginator<int, Usulan>
     */
    /**
     * @param  array{status: ?string, pelaksana: string, perjalanan: string, anggaran_min: ?float, anggaran_maks: ?float}  $saring
     */
    private function daftar(string $tahap, ?string $search, ?string $tahun, ?string $bulan, array $saring, string $urut): LengthAwarePaginator
    {
        $total = Keuangan::select('total')->whereColumn('keuangan.id_usulan', 'usulan.id')->limit(1);
        $statusBayar = Keuangan::select('status')->whereColumn('keuangan.id_usulan', 'usulan.id')->limit(1);
        $nama = User::select('nama')->whereColumn('users.id', 'usulan.id_user')->limit(1);

        return $this->dasar($tahap)
            ->with('user.unit', 'kegiatan', 'kategoriPerjadin', 'keuangan', 'dokumen')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->when($bulan, fn ($q) => $q->whereMonth('tanggal_mulai', $bulan))
            ->when($saring['pelaksana'] !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('nama', 'like', "%{$saring['pelaksana']}%")))
            ->when($saring['perjalanan'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('no_usulan', 'like', "%{$saring['perjalanan']}%")
                ->orWhere('lokasi', 'like', "%{$saring['perjalanan']}%")
                ->orWhere('instansi', 'like', "%{$saring['perjalanan']}%")))
            ->when($saring['status'] === 'lunas', fn ($q) => $q->whereHas('keuangan', fn ($k) => $k->where('status', Keuangan::STATUS_LUNAS)))
            ->when($saring['status'] === 'sebagian', fn ($q) => $q->whereHas('keuangan', fn ($k) => $k->where('status', Keuangan::STATUS_SEBAGIAN)))
            ->when($saring['status'] === 'belum', fn ($q) => $q->whereDoesntHave('keuangan', fn ($k) => $k->whereIn('status', [Keuangan::STATUS_LUNAS, Keuangan::STATUS_SEBAGIAN])))
            ->when($saring['anggaran_min'] !== null, fn ($q) => $q->whereHas('keuangan', fn ($k) => $k->where('total', '>=', $saring['anggaran_min'])))
            ->when($saring['anggaran_maks'] !== null, fn ($q) => $q->whereHas('keuangan', fn ($k) => $k->where('total', '<=', $saring['anggaran_maks'])))
            ->when($urut === 'berangkat-terlama', fn ($q) => $q->orderBy('tanggal_mulai'))
            ->when($urut === 'berangkat-terbaru', fn ($q) => $q->orderByDesc('tanggal_mulai'))
            ->when($urut === 'nama-az', fn ($q) => $q->orderBy($nama))
            ->when($urut === 'nama-za', fn ($q) => $q->orderByDesc($nama))
            ->when($urut === 'anggaran-besar', fn ($q) => $q->orderByDesc($total))
            ->when($urut === 'anggaran-kecil', fn ($q) => $q->orderBy($total))
            ->when($urut === 'status', fn ($q) => $q->orderBy($statusBayar))
            ->orderBy('usulan.id')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Kueri dasar tiap tahap.
     *
     * @return Builder<Usulan>
     */
    private function dasar(string $tahap)
    {
        $disetujui = Usulan::whereIn('status', [
            StatusUsulan::Disetujui->value,
            StatusUsulan::Selesai->value,
        ]);

        return match ($tahap) {
            // Sudah berlaku namun uang mukanya belum ditransfer.
            'disetujui' => $disetujui->whereDoesntHave(
                'keuangan',
                fn ($q) => $q->whereNotNull('tanggal_transfer')
            ),

            // Perjalanan sedang berlangsung hari ini.
            'berjalan' => $disetujui
                ->whereDate('tanggal_mulai', '<=', today())
                ->whereDate('tanggal_selesai', '>=', today()),

            // Uang muka sudah cair, pelunasan belum.
            'uang-muka' => $disetujui->whereHas(
                'keuangan',
                fn ($q) => $q->whereNotNull('tanggal_transfer')
                    ->where('status', '!=', Keuangan::STATUS_LUNAS)
            ),

            default => $disetujui->whereHas('keuangan', fn ($q) => $q->lunas()),
        };
    }

    /**
     * @return array<string, int>
     */
    private function jumlahPerTahap(): array
    {
        $jumlah = [];

        foreach (array_keys(self::TAHAP) as $tahap) {
            $jumlah[$tahap] = $this->dasar($tahap)->count();
        }

        return $jumlah;
    }

    /**
     * Tahun yang benar-benar punya perjalanan pada tahap ini, supaya
     * tombol tahunnya tidak menawarkan tahun kosong.
     *
     * @return Collection<int, int>
     */
    private function tahunTersedia(string $tahap): Collection
    {
        return $this->dasar($tahap)
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
    private function jumlahBulan(string $tahap, ?string $tahun): Collection
    {
        return $this->dasar($tahap)
            ->whereNotNull('tanggal_mulai')
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->selectRaw($this->tanggal->bulan('tanggal_mulai').' as bulan, count(*) as jumlah')
            ->groupBy('bulan')
            ->pluck('jumlah', 'bulan')
            ->mapWithKeys(fn ($jumlah, $kunci) => [(int) $kunci => (int) $jumlah]);
    }

    /**
     * Nilai rupiah yang sudah dan belum dibayarkan, untuk kartu ringkasan.
     *
     * @return array<string, float>
     */
    private function nilaiPembayaran(): array
    {
        $keuangan = Keuangan::query()
            ->whereHas('usulan', fn ($q) => $q->whereIn('status', [
                StatusUsulan::Disetujui->value,
                StatusUsulan::Selesai->value,
            ]))
            ->get();

        $terbayar = $keuangan->sum(
            fn (Keuangan $k) => ($k->uangMukaTerbayar() ? $k->uang_muka : 0)
                + ($k->sudahLunas() ? $k->sisa : 0)
        );

        return [
            'anggaran' => (float) $keuangan->sum('total'),
            'terbayar' => (float) $terbayar,
            'sisa' => (float) $keuangan->sum('total') - (float) $terbayar,
        ];
    }
}
