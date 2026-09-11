<?php

namespace App\Http\Controllers;

use App\Enums\KategoriBiaya;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Usulan;
use App\Services\NotifikasiService;
use App\Services\PenyusunNominatif;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Meja kerja PPK: berkas yang menunggu verifikasi dan tanda tangannya.
 *
 * Dipisahkan dari menu Keuangan karena yang dikerjakan berbeda — PPK
 * memutuskan, tim keuangan menyusun angkanya.
 */
class PersetujuanPpkController extends Controller
{
    public function __construct(
        private PenyusunNominatif $penyusun,
        private NotifikasiService $notifikasi,
    ) {}

    /**
     * Rincian biaya perjalanan dinas (Lampiran II).
     *
     * Dipisahkan dari daftar riil karena keduanya dokumen yang berbeda:
     * rincian memuat seluruh komponen kecuali transport lokal, daftar riil
     * hanya memuat transport lokal.
     */
    public function rincianBiaya(Request $request): View
    {
        $cari = $request->input('cari');
        $status = $request->input('status');

        $semua = Usulan::with('user', 'keuangan.rincianBiaya', 'peserta', 'daftarRiil.ppk')
            ->whereHas('keuangan.rincianBiaya')
            ->when($cari, fn ($q) => $q->where(
                fn ($w) => $w->where('no_usulan', 'like', "%{$cari}%")
                    ->orWhere('no_tugas', 'like', "%{$cari}%")
                    ->orWhereHas('user', fn ($u) => $u->where('nama', 'like', "%{$cari}%"))
            ))
            ->latest('tanggal_mulai')
            ->get()
            ->map(fn (Usulan $item) => $this->barisRincian($item));

        $kelompok = [
            'menunggu' => $semua->where('status', 'menunggu')->values(),
            'tervalidasi' => $semua->where('status', 'tervalidasi')->values(),
            'ditandatangani' => $semua->where('status', 'ditandatangani')->values(),
        ];

        if (! array_key_exists((string) $status, $kelompok)) {
            $status = null;
        }

        $periode = $this->kelompokkanPeriode(
            $status ? $kelompok[$status] : $semua,
            fn (array $baris) => $baris['usulan']->tanggal_mulai,
            $request->input('tahun'),
            $request->input('bulan'),
        );

        return view('persetujuan-ppk.rincian-biaya', [
            'daftar' => $periode['daftar'],
            'status' => $status,
            'cari' => $cari,
            'tahun' => $request->input('tahun'),
            'bulan' => $request->input('bulan'),
            'tahunTersedia' => $periode['tahunTersedia'],
            'jumlahBulan' => $periode['jumlahBulan'],
            'jumlah' => [
                'semua' => $semua->count(),
                'menunggu' => $kelompok['menunggu']->count(),
                'tervalidasi' => $kelompok['tervalidasi']->count(),
                'ditandatangani' => $kelompok['ditandatangani']->count(),
            ],
        ]);
    }

    /**
     * Satu baris ringkas per usulan.
     *
     * Rincian komponennya sengaja tidak ikut: yang dikerjakan PPK di sini
     * adalah memutuskan atas dokumennya secara utuh, bukan memeriksa angka
     * baris demi baris — itu pekerjaan tim keuangan pada menu Keuangan.
     *
     * @return array<string, mixed>
     */
    private function barisRincian(Usulan $usulan): array
    {
        $rincian = ($usulan->keuangan?->rincianBiaya ?? collect())
            ->reject(fn ($baris) => $baris->kategori === KategoriBiaya::TransportLokal);

        $berkas = $usulan->daftarRiil->first();
        $belumDivalidasi = $rincian->whereNull('divalidasi_at')->count();

        return [
            'usulan' => $usulan,
            'berkas' => $berkas,
            'total' => (float) $rincian->sum('jumlah'),
            'belum_divalidasi' => $belumDivalidasi,
            'peserta' => $usulan->peserta->firstWhere('id_user', $usulan->id_user) ?? $usulan->peserta->first(),
            // Jalur rincian punya tanda tangannya sendiri: menandatangani
            // daftar riil tidak ikut mengesahkan dokumen ini.
            'jalur' => $berkas?->jalurRincian(),
            'status' => match (true) {
                (bool) $berkas?->jalurRincian()->sudahDitandatangani() => 'ditandatangani',
                $belumDivalidasi === 0 => 'tervalidasi',
                default => 'menunggu',
            },
        ];
    }

    /**
     * Daftar pengeluaran riil, dikelompokkan menurut apa yang perlu
     * dikerjakan PPK saat ini.
     */
    public function daftarRiil(Request $request): View
    {
        $status = $request->input('status', 'perlu-tindakan');
        $cari = $request->input('cari');

        $semua = DaftarRiil::with('usulan.user', 'peserta', 'rincian')
            ->where('total_riil', '>', 0)
            ->when($cari, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$cari}%")
                    ->orWhere('no_tugas', 'like', "%{$cari}%")
                    ->orWhereHas('user', fn ($p) => $p->where('nama', 'like', "%{$cari}%"))
            ))
            ->latest('diajukan_at')
            ->get();

        $kelompok = [
            'perlu-tindakan' => $semua->filter(fn (DaftarRiil $d) => $this->perluTindakan($d))->values(),
            'menunggu-keuangan' => $semua->filter(fn (DaftarRiil $d) => $this->masihDiKeuangan($d))->values(),
            'menunggu-pelaksana' => $semua->filter(fn (DaftarRiil $d) => $d->masaSanggahBerjalan())->values(),
            'disanggah' => $semua->filter(fn (DaftarRiil $d) => $d->sedangDisanggah())->values(),
            'selesai' => $semua->filter(fn (DaftarRiil $d) => $d->sudah_ditandatangani)->values(),
        ];

        if (! array_key_exists($status, $kelompok)) {
            $status = 'perlu-tindakan';
        }

        $periode = $this->kelompokkanPeriode(
            $kelompok[$status],
            fn (DaftarRiil $daftar) => $daftar->usulan?->tanggal_mulai,
            $request->input('tahun'),
            $request->input('bulan'),
        );

        return view('persetujuan-ppk.daftar-riil', [
            'status' => $status,
            'cari' => $cari,
            'daftar' => $periode['daftar'],
            'tahun' => $request->input('tahun'),
            'bulan' => $request->input('bulan'),
            'tahunTersedia' => $periode['tahunTersedia'],
            'jumlahBulan' => $periode['jumlahBulan'],
            'jumlah' => collect($kelompok)->map->count()->all(),
            'label' => [
                'perlu-tindakan' => 'Perlu Tindakan',
                'menunggu-keuangan' => 'Diproses Tim Keuangan',
                'menunggu-pelaksana' => 'Menunggu Pelaksana',
                'disanggah' => 'Disanggah',
                'selesai' => 'Selesai',
            ],
        ]);
    }

    /**
     * Menunggu keputusan PPK: belum diverifikasi, atau sudah disetujui
     * pelaksana dan tinggal ditandatangani.
     */
    private function perluTindakan(DaftarRiil $daftar): bool
    {
        // Berkas yang belum divalidasi dan dikirim tim keuangan bukan
        // urusan PPK: antriannya hanya memuat yang benar-benar siap
        // ditandatangani atau dikembalikan.
        return ! $daftar->sudah_ditandatangani && $daftar->siapDitandatanganiPpk();
    }

    /**
     * Berkas yang masih di tangan tim keuangan — belum dikirim ke pelaksana,
     * atau dikembalikan PPK untuk diperiksa ulang. Ditampilkan agar PPK tahu
     * berkasnya berjalan, tanpa memberinya tombol yang belum boleh ditekan.
     */
    private function masihDiKeuangan(DaftarRiil $daftar): bool
    {
        return ! $daftar->sudah_ditandatangani
            && ! $daftar->sudahDikirimKePegawai()
            && ! $daftar->sedangDisanggah();
    }

    // ── Daftar nominatif ──

    /**
     * Daftar nominatif per surat tugas. Yang belum terbit ikut ditampilkan
     * sebagai calon, supaya PPK melihat surat tugas mana yang tinggal
     * menunggu satu-dua pelaksana melengkapi dokumennya.
     */
    public function nominatif(Request $request): View
    {
        $this->penyusun->terbitkanYangSiap();

        // Daftar lompat memuat seluruh surat tugas — satu kueri, dipakai
        // untuk mengisi pilihan sekaligus memeriksa fokus yang diminta.
        $suratTugas = DaftarNominatif::latest()->get(['id', 'no_tugas', 'ditandatangani_at', 'dikirim_at']);

        // Satu surat tugas dapat dibuka langsung lewat tautan lompat, tanpa
        // menggulung seluruh daftar.
        $fokus = $request->input('surat-tugas');

        if ($fokus && ! $suratTugas->contains('no_tugas', $fokus)) {
            $fokus = null;
        }

        $tandaTangan = in_array($request->input('tanda-tangan'), ['belum', 'sudah'], true)
            ? $request->input('tanda-tangan')
            : null;

        $cari = $request->input('cari');

        $tahun = $request->input('tahun');
        $bulan = $request->input('bulan');

        // Periode dibaca dari tanggal surat tugasnya; yang belum bertanggal
        // ikut tampil bila tidak ada saringan periode.
        $dasar = fn () => DaftarNominatif::query()
            ->when($fokus, fn ($q) => $q->where('no_tugas', $fokus))
            ->when($cari, fn ($q) => $q->where('no_tugas', 'like', "%{$cari}%"))
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_tugas', $tahun))
            ->when($bulan, fn ($q) => $q->whereMonth('tanggal_tugas', $bulan));

        // Barisnya disusun dari usulan, bukan disimpan, sehingga tiap daftar
        // nominatif yang tampil membawa rangkaian kuerinya sendiri. Karena
        // itu halaman ini dibatasi, dan barisnya dimuat sekali untuk seluruh
        // surat tugas pada halaman berjalan.
        $halaman = $dasar()
            ->with('ppk', 'kategoriPembiayaan', 'akunPembiayaan')
            ->when($tandaTangan === 'belum', fn ($q) => $q->whereNull('ditandatangani_at'))
            ->when($tandaTangan === 'sudah', fn ($q) => $q->whereNotNull('ditandatangani_at'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $baris = $this->penyusun->barisBanyak($halaman->pluck('no_tugas'));
        $menunggu = $this->penyusun->menungguBanyak($halaman->pluck('no_tugas'));

        $muat = fn (DaftarNominatif $daftar) => [
            'daftar' => $daftar,
            'baris' => $baris->get($daftar->no_tugas) ?? collect(),
            'menunggu' => $menunggu->get($daftar->no_tugas) ?? collect(),
        ];

        $tampil = $halaman->getCollection();

        // Tahun dan bulan yang tersedia dihitung dari seluruh surat tugas yang
        // memenuhi pencarian, bukan hanya halaman berjalan.
        $tanggalSemua = DaftarNominatif::query()
            ->when($fokus, fn ($q) => $q->where('no_tugas', $fokus))
            ->when($cari, fn ($q) => $q->where('no_tugas', 'like', "%{$cari}%"))
            ->whereNotNull('tanggal_tugas')
            ->pluck('tanggal_tugas');

        $perPeriode = fn (Collection $isi) => $isi
            ->groupBy(fn (array $entri) => $entri['daftar']->tanggal_tugas?->translatedFormat('F Y') ?? 'Tanpa Tanggal');

        return view('persetujuan-ppk.nominatif', [
            'tandaTangan' => $tandaTangan,
            'cari' => $cari,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'tahunTersedia' => $tanggalSemua->map(fn ($t) => Carbon::parse($t)->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $tanggalSemua
                ->when($tahun, fn (Collection $t) => $t->filter(fn ($x) => Carbon::parse($x)->year === (int) $tahun))
                ->countBy(fn ($t) => Carbon::parse($t)->month),
            'halaman' => $halaman,
            'belum' => $perPeriode($tampil->reject->sudahDitandatangani()->map($muat)->values()),
            'sudah' => $perPeriode($tampil->filter->sudahDitandatangani()->map($muat)->values()),
            'jumlahBelum' => $dasar()->whereNull('ditandatangani_at')->count(),
            'jumlahSudah' => $dasar()->whereNotNull('ditandatangani_at')->count(),
            'suratTugas' => $suratTugas->map(fn (DaftarNominatif $d) => [
                'no' => $d->no_tugas,
                'label' => $d->no_tugas.' — '.$d->status_label,
            ])->values(),
            'fokus' => $fokus,
        ]);
    }

    public function nominatifDetail(DaftarNominatif $nominatif): View
    {
        $baris = $this->penyusun->baris($nominatif->no_tugas);

        return view('persetujuan-ppk.nominatif-detail', [
            'nominatif' => $nominatif,
            'baris' => $baris,
            'total' => $this->penyusun->total($baris),
            'menunggu' => $this->penyusun->menunggu($nominatif->no_tugas),
        ]);
    }

    public function tandaTanganiNominatif(Request $request, DaftarNominatif $nominatif): RedirectResponse
    {
        if ($nominatif->sudahDitandatangani()) {
            return back()->with('error', 'Daftar nominatif ini sudah ditandatangani.');
        }

        $nominatif->update([
            'id_ppk' => $request->user()->id,
            'ditandatangani_at' => now(),
        ]);

        // Kode ini menjadi QR pada cetakannya, menggantikan penanda tanda
        // tangan yang kosong sebelum disahkan.
        $nominatif->terbitkanKodeVerifikasi();

        return back()->with('success', 'Daftar nominatif ditandatangani. Kirimkan ke tim keuangan bila sudah final.');
    }

    /**
     * Mengirim ke tim keuangan adalah langkah terpisah dari menandatangani:
     * PPK dapat memeriksa hasil tanda tangannya lebih dulu.
     */
    public function kirimNominatif(Request $request, DaftarNominatif $nominatif): RedirectResponse
    {
        if (! $nominatif->sudahDitandatangani()) {
            return back()->with('error', 'Tandatangani daftar nominatif lebih dulu sebelum mengirimkannya.');
        }

        if ($nominatif->sudahDikirim()) {
            return back()->with('error', 'Daftar nominatif ini sudah dikirim ke tim keuangan.');
        }

        $nominatif->update(['dikirim_at' => now()]);

        // Tim keuangan yang memprosesnya menjadi pembayaran; Tim SDM tetap
        // dapat membacanya sebagai arsip lewat menu Laporan.
        $this->notifikasi->kirimKePeran(
            [User::ROLE_TIM_KEUANGAN],
            'Daftar nominatif diterima dari PPK',
            "Daftar nominatif surat tugas {$nominatif->no_tugas} sudah ditandatangani PPK dan dapat diproses di menu Laporan.",
            [
                'tipe' => Notifikasi::TIPE_INFO,
                'url' => route('laporan.nominatif'),
            ],
        );

        return back()->with('success', 'Daftar nominatif dikirim ke tim keuangan.');
    }

    // ── Riwayat tanda tangan ──

    /**
     * Seluruh dokumen yang pernah ditandatangani PPK, terbaru lebih dulu.
     *
     * Dua jenis berkas dikumpulkan jadi satu lini masa: berkas
     * pertanggungjawaban per pelaksana, dan daftar nominatif per surat
     * tugas. Keduanya ditandatangani PPK, jadi menampilkannya terpisah
     * hanya memaksa PPK mencari di dua tempat.
     */
    public function riwayat(Request $request): View
    {
        $jenis = in_array($request->input('jenis'), ['berkas', 'nominatif'], true)
            ? $request->input('jenis')
            : null;

        $berkas = DaftarRiil::with('usulan', 'peserta', 'ppk')
            ->whereNotNull('ditandatangani_at')
            ->get()
            ->map(fn (DaftarRiil $item) => [
                'jenis' => 'berkas',
                'label' => 'Rincian Biaya & Daftar Riil',
                'nomor' => $item->usulan?->no_usulan ?? '—',
                'keterangan' => $item->peserta?->nama,
                'nilai' => (float) $item->total_riil,
                'waktu' => $item->ditandatangani_at,
                'ppk' => $item->ppk,
                'tautan' => $item->usulan
                    ? route('daftar-riil.cetak', [$item->usulan, $item->peserta])
                    : null,
            ]);

        $nominatif = DaftarNominatif::with('ppk')
            ->whereNotNull('ditandatangani_at')
            ->get()
            ->map(fn (DaftarNominatif $item) => [
                'jenis' => 'nominatif',
                'label' => 'Daftar Nominatif',
                'nomor' => $item->no_tugas,
                'keterangan' => $item->sudahDikirim()
                    ? 'Terkirim ke tim keuangan'
                    : 'Belum dikirim ke tim keuangan',
                'nilai' => null,
                'waktu' => $item->ditandatangani_at,
                'ppk' => $item->ppk,
                'tautan' => route('laporan.nominatif.cetak', $item),
            ]);

        $riwayat = $berkas->concat($nominatif)
            ->when($jenis, fn ($k) => $k->where('jenis', $jenis))
            ->sortByDesc('waktu')
            ->values();

        return view('persetujuan-ppk.riwayat', [
            'riwayat' => $riwayat,
            'jenis' => $jenis,
            'jumlah' => [
                'semua' => $berkas->count() + $nominatif->count(),
                'berkas' => $berkas->count(),
                'nominatif' => $nominatif->count(),
            ],
        ]);
    }

    /**
     * Saring baris menurut tahun/bulan lalu kelompokkan per periode
     * ("September 2026"), diurutkan dari yang terbaru.
     *
     * Tab status tetap dihitung dari seluruh baris; saringan periode hanya
     * mengurangi yang ditampilkan — angka tab dan angka bulan menjawab
     * pertanyaan yang berbeda.
     *
     * @param  Collection<int, mixed>  $baris
     * @param  callable(mixed): mixed  $tanggalDari  Mengambil tanggal (string|Carbon|null) dari satu baris.
     * @return array{daftar: Collection<string, Collection<int, mixed>>, tahunTersedia: Collection<int, int>, jumlahBulan: Collection<int, int>}
     */
    private function kelompokkanPeriode(Collection $baris, callable $tanggalDari, ?string $tahun, ?string $bulan): array
    {
        $bertanggal = $baris->map(fn ($item) => [
            'item' => $item,
            'tanggal' => ($t = $tanggalDari($item)) ? Carbon::parse($t) : null,
        ]);

        $tampil = $bertanggal
            ->when($tahun, fn (Collection $b) => $b->filter(fn (array $x) => $x['tanggal']?->year === (int) $tahun))
            ->when($bulan, fn (Collection $b) => $b->filter(fn (array $x) => $x['tanggal']?->month === (int) $bulan))
            ->sortByDesc(fn (array $x) => $x['tanggal']?->timestamp ?? 0)
            ->groupBy(fn (array $x) => $x['tanggal']?->translatedFormat('F Y') ?? 'Tanpa Tanggal')
            ->map(fn (Collection $kelompok) => $kelompok->pluck('item')->values());

        return [
            'daftar' => $tampil,
            'tahunTersedia' => $bertanggal->pluck('tanggal')->filter()
                ->map(fn (Carbon $t) => $t->year)->unique()->sortDesc()->values(),
            'jumlahBulan' => $bertanggal
                ->when($tahun, fn (Collection $b) => $b->filter(fn (array $x) => $x['tanggal']?->year === (int) $tahun))
                ->pluck('tanggal')->filter()
                ->countBy(fn (Carbon $t) => $t->month),
        ];
    }
}
