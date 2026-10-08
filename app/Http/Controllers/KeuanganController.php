<?php

namespace App\Http\Controllers;

use App\Enums\CaraBayarBiaya;
use App\Enums\DokumenCetak;
use App\Enums\IsianBiaya;
use App\Enums\KategoriBiaya;
use App\Models\AuditLog;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\KomponenBiaya;
use App\Models\Notifikasi;
use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\RiwayatPembayaran;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use App\Services\PemberitahuanBendahara;
use App\Services\PemegangNominal;
use App\Services\PemulihRincian;
use App\Services\PenagihDokumen;
use App\Services\PencatatTransportLokal;
use App\Services\PengaturanDokumen;
use App\Services\PengirimanBerkas;
use App\Services\PenguncianBerkas;
use App\Services\QrCodeService;
use App\Services\RingkasanPembayaran;
use App\Services\SinkronBiayaDokumen;
use App\Services\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class KeuanganController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
        private Terbilang $terbilang,
        private PenagihDokumen $penagih,
        private QrCodeService $qrCode,
        private PemberitahuanBendahara $bendahara,
        private SinkronBiayaDokumen $sinkron,
        private PenguncianBerkas $kunci,
        private PengirimanBerkas $pengiriman,
        private PemegangNominal $pemegang,
        private PencatatTransportLokal $transportLokal,
        private PemulihRincian $pemulih,
        private RingkasanPembayaran $ringkasan,
    ) {}

    /**
     * Tahap pengerjaan keuangan sebuah perjadin, berurutan.
     *
     * Ditentukan dari berkasnya: belum dikirim ke pelaksana berarti masih
     * diproses tim keuangan; sudah dikirim tetapi rincian belum disahkan
     * PPK berarti menunggu pelaksana dan PPK; sudah disahkan berarti tinggal
     * dibayarkan; lunas berarti selesai.
     *
     * @var array<string, array{label: string, badge: string}>
     */
    private const TAHAP = [
        'diproses' => ['label' => 'Sedang Diproses', 'badge' => 'bg-amber-100 text-amber-700'],
        'dikirim' => ['label' => 'Dikirim ke Pelaksana', 'badge' => 'bg-sky-100 text-sky-700'],
        'pembayaran' => ['label' => 'Proses Pembayaran', 'badge' => 'bg-blue-100 text-blue-700'],
        'selesai' => ['label' => 'Selesai', 'badge' => 'bg-violet-100 text-violet-700'],
    ];

    /**
     * Pilihan urutan daftar perjadin.
     *
     * @var array<string, string>
     */
    private const URUTAN = [
        'terbaru' => 'Usulan terbaru',
        'terlama' => 'Usulan terlama',
        'berangkat-terbaru' => 'Tanggal berangkat terbaru',
        'berangkat-terlama' => 'Tanggal berangkat terlama',
        'nomor' => 'Nomor usulan (A–Z)',
    ];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = array_key_exists((string) $request->input('status'), self::TAHAP) ? $request->input('status') : null;
        $urut = array_key_exists((string) $request->input('urut'), self::URUTAN) ? $request->input('urut') : 'terbaru';

        // Perjadin yang sudah selesai tetap dibuka di sini: tim keuangan
        // masih membutuhkannya untuk menelusuri berkas dan mencetak ulang
        // dokumennya. Tabnya membuat keduanya terjangkau sekali klik.
        $dasar = fn () => Usulan::whereIn('status', ['disetujui', 'selesai'])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('no_usulan', 'like', "%{$search}%")
                    ->orWhere('no_tugas', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhere('instansi', 'like', "%{$search}%")
                    ->orWhereHas('kegiatan', fn ($k) => $k->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('nama', 'like', "%{$search}%"));
            }));

        $jumlahStatus = collect(self::TAHAP)
            ->map(fn (array $tahap, string $kunci) => $this->saringTahap($dasar(), $kunci)->count())
            ->all();

        $usulan = $dasar()
            ->with('user', 'kegiatan', 'kategoriPerjadin', 'keuangan', 'dokumen', 'spd', 'daftarRiil')
            ->when($status, fn ($q) => $this->saringTahap($q, $status))
            ->when($urut === 'terbaru', fn ($q) => $q->latest())
            ->when($urut === 'terlama', fn ($q) => $q->oldest())
            ->when($urut === 'berangkat-terbaru', fn ($q) => $q->latest('tanggal_mulai'))
            ->when($urut === 'berangkat-terlama', fn ($q) => $q->oldest('tanggal_mulai'))
            ->when($urut === 'nomor', fn ($q) => $q->orderBy('no_usulan'))
            ->paginate(10)
            ->withQueryString();

        // Tautan penagihan berkas disiapkan di sini agar Blade tidak perlu
        // menghitung ulang kelengkapan dokumen tiap baris.
        $usulan->getCollection()->each(function (Usulan $item): void {
            $item->berkas_kurang = $this->penagih->berkasKurang($item);
            $item->tautan_wa = $this->penagih->tautanWhatsapp($item);
            $item->alasan_tanpa_wa = $this->penagih->alasanTidakTersedia($item);
            $item->tahap_keuangan = self::TAHAP[$this->tahapDari($item)];
        });

        return view('keuangan.keuangan', [
            'usulan' => $usulan,
            'status' => $status,
            'urut' => $urut,
            'pilihanUrut' => self::URUTAN,
            'tahap' => self::TAHAP,
            'jumlahStatus' => $jumlahStatus,
        ]);
    }

    /**
     * Saring perjadin pada satu tahap. Tahapnya saling lepas: tiap perjadin
     * hanya berada pada tahap terjauh yang sudah dicapainya.
     *
     * @param  Builder<Usulan>  $query
     * @return Builder<Usulan>
     */
    private function saringTahap(Builder $query, string $tahap): Builder
    {
        $selesai = fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('status', 'selesai')
            ->orWhereHas('keuangan', fn (Builder $k) => $k->where('status', Keuangan::STATUS_LUNAS)));
        $disahkan = fn (Builder $q) => $q->whereHas('daftarRiil', fn (Builder $d) => $d->whereNotNull('rincian_ditandatangani_at'));
        $dikirim = fn (Builder $q) => $q->whereHas('daftarRiil', fn (Builder $d) => $d->whereNotNull('dikirim_ke_pegawai_at'));

        return match ($tahap) {
            'selesai' => $selesai($query),
            'pembayaran' => $disahkan($query->whereNot($selesai)),
            'dikirim' => $dikirim($query->whereNot($selesai)->whereNot($disahkan)),
            default => $query->whereNot($selesai)->whereNot($disahkan)->whereNot($dikirim),
        };
    }

    /**
     * Tahap satu perjadin yang relasinya sudah dimuat — padanan saringTahap().
     */
    private function tahapDari(Usulan $usulan): string
    {
        return match (true) {
            $usulan->status === 'selesai' || $usulan->keuangan?->sudahLunas() => 'selesai',
            $usulan->daftarRiil->contains(fn (DaftarRiil $d) => $d->rincian_ditandatangani_at !== null) => 'pembayaran',
            $usulan->daftarRiil->contains(fn (DaftarRiil $d) => $d->dikirim_ke_pegawai_at !== null) => 'dikirim',
            default => 'diproses',
        };
    }

    /**
     * Transport lokal seluruh pelaksana, dari sisi keuangan.
     *
     * Bermenu sendiri karena ia tidak masuk rincian biaya: nominalnya
     * dinyatakan pelaksana pada Daftar Pengeluaran Riil, lalu dibayarkan
     * sebagai penggantian saat pelunasan.
     */
    public function transportLokal(Request $request)
    {
        $cari = $request->input('cari');

        $daftar = DaftarRiil::with('usulan.user', 'usulan.notaTransport', 'peserta', 'ppk', 'rincian')
            ->where('total_riil', '>', 0)
            ->when($cari, fn ($q) => $q->whereHas(
                'usulan',
                fn ($u) => $u->where('no_usulan', 'like', "%{$cari}%")
                    ->orWhere('no_tugas', 'like', "%{$cari}%")
            )->orWhereHas('peserta', fn ($p) => $p->where('nama', 'like', "%{$cari}%")))
            ->latest('diajukan_at')
            ->paginate(20)
            ->withQueryString();

        return view('keuangan.transport-lokal', [
            'daftar' => $daftar,
            'cari' => $cari,

            // Yang sudah ditandatangani kedua pihak sajalah yang benar-benar
            // menjadi kewajiban bayar.
            'totalTerkunci' => (float) DaftarRiil::whereNotNull('ditandatangani_at')->sum('total_riil'),
            'totalBerjalan' => (float) DaftarRiil::whereNull('ditandatangani_at')->sum('total_riil'),
        ]);
    }

    public function show(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen', 'peserta', 'daftarRiil.rincian', 'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');

        if (! $usulan->keuangan) {
            $usulan->keuangan()->create([
                'total' => 0,
                'uang_muka' => 0,
                'sisa' => 0,
                'status' => 'belum bayar',
            ]);
            $usulan->load('peserta', 'daftarRiil.rincian', 'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');
        }

        // Nominal yang diisi pelaksana harus selalu tampil pada tabelnya agar
        // dapat diperiksa dan dikoreksi. Baris yang tertinggal — terhapus,
        // atau penyalinannya dulu gagal — disalin ulang saat halaman dibuka.
        if ($this->pemulih->perlu($usulan) && $this->kunci->unggahanPelaksana($usulan) === null) {
            $this->pemulih->pulihkan($usulan);
            $usulan->load('dokumen', 'peserta', 'daftarRiil.rincian', 'keuangan.rincianBiaya', 'keuangan.dokumenKeuangan');
            session()->now('success', 'Nominal dari berkas pelaksana yang belum tercatat sudah disalin ke rincian biaya dan transport lokal.');
        }

        // Standar biaya dipakai untuk mengisi otomatis satuan dan harga di form rincian.
        $komponenBiaya = KomponenBiaya::aktif()->orderBy('nama')->get();
        $kategoriBiaya = KategoriBiaya::untukTimKeuangan();

        // Siapa membayar komponen mana — untuk tabel status bayar dan kartu
        // ringkasan bendahara — serta baris berkas yang dihapus tim keuangan.
        $ringkasanBayar = $this->ringkasan->untuk($usulan);
        $rincianTerhapus = $usulan->keuangan->rincianBiaya()->onlyTrashed()->latest('deleted_at')->get();

        $data = compact('usulan', 'komponenBiaya', 'kategoriBiaya', 'ringkasanBayar', 'rincianTerhapus');

        if ($usulan->keuangan->status === 'lunas') {
            return view('keuangan.paid', $data);
        }

        if ($usulan->keuangan->status === 'bayar sebagian') {
            return view('keuangan.partial-paid', $data);
        }

        return view('keuangan.unpaid', $data);
    }

    /**
     * Cetak rincian biaya sesuai format Lampiran II PMK 113/PMK.05/2012.
     */
    public function cetakRincian(Request $request, Usulan $usulan): Response
    {
        $usulan->load('user.unit', 'kegiatan', 'keuangan.rincianBiaya', 'peserta');

        // Hanya baris sah yang tercetak: tulisan tim keuangan dan nominal
        // pelaksana yang sudah divalidasi. Transport lokal tidak termasuk —
        // ia dicetak pada Daftar Pengeluaran Riil.
        $rincian = ($usulan->keuangan?->rincianBiaya ?? collect())
            ->filter(fn (RincianBiaya $item) => $item->terhitung())
            ->values();

        $total = (float) $rincian->sum('jumlah');

        // Kelompokkan menurut kategori resmi dan urutkan sesuai penomoran PMK.
        $rincianPerKategori = $rincian
            ->groupBy(fn (RincianBiaya $item) => $item->kategori->value)
            ->sortBy(fn ($baris, $kategori) => KategoriBiaya::dari($kategori)->urutan());

        // Peserta tertentu bila dicetak per orang, selain itu pengusul.
        $peserta = $request->filled('peserta')
            ? $usulan->peserta->firstWhere('id', $request->integer('peserta'))
            : $usulan->peserta->firstWhere('peran', 'ketua');

        // Konfirmasi pelaksana baru dicetak setelah PPK menandatangani daftar
        // riilnya, dan isinya sama persis dengan QR pada daftar riil tersebut.
        $daftarRiil = $peserta
            ? DaftarRiil::where('id_peserta', $peserta->id)->sudahDitandatangani()->first()
            : null;

        // Berkas peserta ini pada tahap mana pun — sumber tanggal pelaksana
        // menyetujui rinciannya.
        $berkasPeserta = $peserta
            ? DaftarRiil::where('id_peserta', $peserta->id)->where('id_usulan', $usulan->id)->first()
            : null;

        $keuangan = $usulan->keuangan;

        // Yang sudah dibayarkan atas rincian ini: uang muka, ditambah sisanya
        // bila sudah dilunasi. Penggantian transport lokal tercatat pada
        // Daftar Pengeluaran Riil, bukan di sini.
        $dibayarkan = (float) ($keuangan?->uang_muka ?? 0)
            + ($keuangan?->tanggal_pelunasan ? (float) $keuangan->sisa : 0);

        $pdf = Pdf::loadView('keuangan.cetak-rincian', [
            'usulan' => $usulan,
            'peserta' => $peserta,
            'rincianPerKategori' => $rincianPerKategori,
            'total' => $total,
            // Tanggal pelaksana menyetujui rincian ini — tercetak di atas tanda
            // tangannya, dari daftar riil pada tahap mana pun.
            'tanggalPelaksana' => $berkasPeserta?->rincian_disetujui_at ?? $berkasPeserta?->disetujui_pegawai_at,
            'dibayarkan' => $dibayarkan,
            'terbilang' => $this->terbilang->konversi($total),
            'bendahara' => User::where('role', User::ROLE_BENDAHARA)->first(),
            'ppk' => User::where('role', User::ROLE_PPK)->first(),
            'daftarRiil' => $daftarRiil,
            'keuangan' => $keuangan,
            'qrPelaksana' => $daftarRiil?->urlKonfirmasi()
                ? $this->qrCode->dataUri($daftarRiil->urlKonfirmasi(), 180)
                : null,
            'qrPpk' => $daftarRiil?->urlVerifikasi()
                ? $this->qrCode->dataUri($daftarRiil->urlVerifikasi(), 180)
                : null,
            'qrBendahara' => $keuangan?->urlKonfirmasiBayar()
                ? $this->qrCode->dataUri($keuangan->urlKonfirmasiBayar(), 180)
                : null,
        ])->setPaper(app(PengaturanDokumen::class)->untuk(DokumenCetak::RincianBiaya)->kertas());

        return $pdf->download("Rincian-Biaya_{$usulan->no_usulan}.pdf");
    }

    /**
     * Simpan rincian biaya baru.
     */
    public function storeRincian(Request $request, Usulan $usulan)
    {
        $this->pastikanBolehMengelolaBiaya($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        // Angka yang sudah ditandatangani tidak boleh bergeser: dokumen
        // tercetak dan daftar nominatif menumpang di atasnya.
        $this->kunci->pastikanRincianTerbuka($usulan);

        $request->validate(...$this->aturanRincianKeuangan($request));

        // Transport lokal dicatat pada Daftar Pengeluaran Riil pelaksana,
        // bukan sebagai baris rincian biaya.
        if ($request->input('kategori') === KategoriBiaya::TransportLokal->value) {
            $this->transportLokal->tambah($usulan, $request->komponen, (float) ($request->volume * $request->harga_satuan));

            $this->audit->catat(
                AuditLog::AKSI_BIAYA,
                "Transport lokal \"{$request->komponen}\" dicatat tim keuangan pada daftar riil usulan {$usulan->no_usulan}.",
                ['usulan' => $usulan],
            );

            return redirect()->route('keuangan.detail', $usulan->no_usulan)
                ->with('success', 'Komponen dicatat pada Transport Lokal (Daftar Pengeluaran Riil).');
        }

        // Komponen yang sudah diisi pelaksana beserta buktinya tidak ditulis
        // ulang di sini — baris dari berkasnya itulah yang diperiksa.
        $isian = IsianBiaya::untukBarisKeuangan(KategoriBiaya::from($request->kategori), $request->input('isian_pelaksana'));
        $bentrok = $this->pemegang->bentrokDenganPelaksana($usulan, $isian);

        if ($bentrok !== []) {
            throw ValidationException::withMessages(['isian_pelaksana' => $this->pemegang->pesanBentrok($usulan, $bentrok)]);
        }

        $keuangan = $usulan->keuangan;

        $keuangan->rincianBiaya()->create([
            'kategori' => $request->kategori,
            'komponen' => $request->komponen,
            'volume' => $request->volume,
            'satuan' => $request->satuan,
            'harga_satuan' => $request->harga_satuan,
            'jumlah' => $request->volume * $request->harga_satuan,
            'keterangan' => $request->keterangan,
            'isian_pelaksana' => $isian?->value,
        ]);

        $keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Komponen biaya \"{$request->komponen}\" ditambahkan pada usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        // Bendahara diberi tahu bahwa uang muka 80% sudah dapat ditransfer.
        $this->bendahara->rincianSiapDibayar($usulan->fresh('keuangan', 'user'));

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Rincian biaya berhasil ditambahkan.');
    }

    /**
     * Update rincian biaya.
     */
    public function updateRincian(Request $request, Usulan $usulan, RincianBiaya $rincian)
    {
        $this->pastikanBolehMengelolaBiaya($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        // Angka yang sudah ditandatangani tidak boleh bergeser: dokumen
        // tercetak dan daftar nominatif menumpang di atasnya.
        $this->kunci->pastikanRincianTerbuka($usulan);

        $this->pastikanRincianMilikUsulan($usulan, $rincian);

        // Baris dari berkas pelaksana adalah isian pelaksana itu sendiri: tim
        // keuangan boleh mengoreksi angkanya, tetapi tidak memilihkan isiannya.
        $dariBerkas = $rincian->dariDokumen();

        $request->validate(...$this->aturanRincianKeuangan($request, $dariBerkas));

        // Dipindah ke transport lokal: barisnya pindah ke Daftar Pengeluaran
        // Riil, tidak lagi tersembunyi di rincian biaya.
        if ($request->input('kategori') === KategoriBiaya::TransportLokal->value) {
            try {
                $this->transportLokal->pindahkan($usulan, $rincian, $request->komponen, (float) ($request->volume * $request->harga_satuan));
            } catch (ValidationException $e) {
                return redirect()->route('keuangan.detail', $usulan->no_usulan)
                    ->with('error', collect($e->errors())->flatten()->first());
            }

            $this->audit->catat(
                AuditLog::AKSI_BIAYA,
                "Komponen biaya \"{$rincian->komponen}\" pada usulan {$usulan->no_usulan} dipindahkan ke transport lokal.",
                ['usulan' => $usulan],
            );

            return redirect()->route('keuangan.detail', $usulan->no_usulan)
                ->with('success', "Komponen \"{$request->komponen}\" dipindahkan ke Transport Lokal (Daftar Pengeluaran Riil).");
        }

        $isian = $dariBerkas
            ? null
            : IsianBiaya::untukBarisKeuangan(KategoriBiaya::from($request->kategori), $request->input('isian_pelaksana'));
        $bentrok = $this->pemegang->bentrokDenganPelaksana($usulan, $isian, $rincian->isian_pelaksana);

        // Baris sunting tersembunyi di dalam tabel, jadi penolakannya
        // disampaikan lewat pesan di puncak halaman.
        if ($bentrok !== []) {
            return redirect()->route('keuangan.detail', $usulan->no_usulan)
                ->with('error', $this->pemegang->pesanBentrok($usulan, $bentrok));
        }

        $rincian->update([
            'kategori' => $request->kategori,
            'komponen' => $request->komponen,
            'volume' => $request->volume,
            'satuan' => $request->satuan,
            'harga_satuan' => $request->harga_satuan,
            'jumlah' => $request->volume * $request->harga_satuan,
            'keterangan' => $request->keterangan,
        ] + ($dariBerkas ? [] : ['isian_pelaksana' => $isian?->value]));

        $usulan->keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Komponen biaya \"{$rincian->komponen}\" pada usulan {$usulan->no_usulan} diperbarui.",
            ['usulan' => $usulan],
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Rincian biaya berhasil diperbarui.');
    }

    /**
     * Hapus komponen rincian biaya. Hak hapusnya dijaga rute.
     *
     * Baris dari berkas pelaksana ditandai terhapus, bukan dibuang: dengan
     * begitu ia tidak tersalin lagi dari berkasnya setiap kali halaman
     * dibuka, dan dapat dikembalikan bila keliru. Bila pelaksana kemudian
     * mengubah nominalnya, baris itu tampil lagi untuk diperiksa.
     */
    public function destroyRincian(Request $request, Usulan $usulan, RincianBiaya $rincian)
    {
        $this->pastikanBolehMengelolaBiaya($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');
        $this->pastikanRincianMilikUsulan($usulan, $rincian);

        // Angka yang sudah ditandatangani tidak boleh bergeser: dokumen
        // tercetak dan daftar nominatif menumpang di atasnya.
        $this->kunci->pastikanRincianTerbuka($usulan);

        $komponen = $rincian->komponen;
        $dariBerkas = $rincian->dariDokumen();

        if ($dariBerkas) {
            $rincian->delete();
        } else {
            $rincian->forceDelete();
        }

        $usulan->keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Komponen biaya \"{$komponen}\" dihapus dari usulan {$usulan->no_usulan}"
                .($dariBerkas ? ' (nominal dari berkas pelaksana).' : '.'),
            ['usulan' => $usulan],
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', $dariBerkas
                ? "\"{$komponen}\" dari berkas pelaksana dihapus dari rincian biaya dan tidak lagi dihitung. Ia tampil lagi hanya bila pelaksana mengubah nominalnya, dan dapat dikembalikan dari daftar di bawah tabel."
                : 'Rincian biaya berhasil dihapus.');
    }

    /**
     * Kembalikan baris berkas pelaksana yang terhapus ke rincian biaya.
     * Nominalnya diperiksa ulang sebelum terhitung lagi.
     */
    public function kembalikanRincian(Request $request, Usulan $usulan, RincianBiaya $rincian): RedirectResponse
    {
        $this->pastikanBolehMengelolaBiaya($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');
        $this->pastikanRincianMilikUsulan($usulan, $rincian);
        $this->kunci->pastikanRincianTerbuka($usulan);

        abort_unless($rincian->trashed(), 422, 'Baris ini tidak sedang terhapus.');

        $rincian->restore();
        $rincian->update(['divalidasi_at' => null, 'id_validator' => null]);
        $usulan->keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Komponen biaya \"{$rincian->komponen}\" dikembalikan ke rincian biaya usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', "\"{$rincian->komponen}\" dikembalikan ke rincian biaya — periksa lalu validasi lagi nominalnya.");
    }

    /**
     * Tim keuangan mengonfirmasi cara bayar sebuah komponen: lewat uang muka,
     * atau dibayar pelaksana dahulu lalu diganti saat pelunasan.
     *
     * Setelah uang muka ditransfer, pilihan tidak dapat dibalik — uangnya
     * sudah keluar — tetapi cara yang berlaku masih dapat dikonfirmasi.
     */
    public function caraBayarRincian(Request $request, Usulan $usulan, RincianBiaya $rincian): RedirectResponse
    {
        abort_unless($request->user()->bisaMemvalidasiBiaya(), 403, 'Cara bayar komponen dikonfirmasi tim keuangan.');
        $this->pastikanRincianMilikUsulan($usulan, $rincian);

        $validated = $request->validate([
            'cara_bayar' => ['required', Rule::enum(CaraBayarBiaya::class)],
        ], [
            'cara_bayar.required' => 'Pilih cara bayar komponen ini.',
        ]);

        $keuangan = $usulan->keuangan;
        $cara = CaraBayarBiaya::from($validated['cara_bayar']);
        $kembali = redirect()->route('keuangan.detail', $usulan->no_usulan);

        if ($rincian->kategori === KategoriBiaya::UangHarian) {
            return $kembali->with('error', 'Uang harian selalu dibayarkan 80% lewat uang muka dan 20% saat pelunasan.');
        }

        if ($keuangan->sudahLunas()) {
            return $kembali->with('error', 'Pembayaran sudah lunas, jadi cara bayar komponennya tidak diubah lagi.');
        }

        if ($keuangan->uangMukaTerbayar() && $cara !== $rincian->caraBayar()) {
            return $kembali->with('error', "Uang muka sudah ditransfer, jadi cara bayar \"{$rincian->komponen}\" tidak dapat diubah lagi. Koreksi nominal sesudahnya masuk sisa bayar.");
        }

        $rincian->update([
            'cara_bayar' => $cara,
            'cara_bayar_dikonfirmasi_at' => now(),
            'id_pengonfirmasi_bayar' => $request->user()->id,
        ]);
        $keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Cara bayar \"{$rincian->komponen}\" pada usulan {$usulan->no_usulan} dikonfirmasi: {$cara->label()}.",
            ['usulan' => $usulan],
        );

        return $kembali->with('success', "Cara bayar \"{$rincian->komponen}\" dikonfirmasi: {$cara->label()}.");
    }

    /**
     * Hapus transport lokal tulisan tim keuangan dari daftar riil pelaksana.
     */
    public function destroyTransportLokal(Request $request, Usulan $usulan, RincianDaftarRiil $baris): RedirectResponse
    {
        $this->pastikanBolehMengelolaBiaya($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        $uraian = $baris->uraian;
        $this->transportLokal->hapus($usulan, $baris);

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Transport lokal \"{$uraian}\" tulisan tim keuangan dihapus dari daftar riil usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', "Transport lokal \"{$uraian}\" dihapus.");
    }

    /**
     * Konfirmasi pembayaran uang muka (belum bayar → bayar sebagian).
     */
    public function bayarUangMuka(Request $request, Usulan $usulan)
    {
        $this->pastikanBolehMencatatPembayaran($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        $request->validate([
            'tanggal_transfer' => ['required', 'date'],
            'bukti_transfer' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $keuangan = $usulan->keuangan;

        // Menekan tombolnya dua kali — atau dua bendahara mengerjakan berkas
        // yang sama — dulu menimpa tanggal transfer lama dan menambah baris
        // jurnal kedua. Perbaikan yang benar lewat pembatalan, bukan timpa.
        if ($keuangan?->uangMukaTerbayar()) {
            return back()->with('error', 'Uang muka usulan ini sudah tercatat pada '
                .$keuangan->tanggal_transfer->translatedFormat('d F Y')
                .'. Batalkan pembayarannya lebih dulu bila keliru.');
        }
        $path = $request->file('bukti_transfer')->store('keuangan/uang-muka', 'public');

        // Nominal yang ditransfer dihitung dari baris sah saat ini, lalu
        // dicatat komponen mana saja yang ikut di dalamnya.
        $keuangan->hitungTotal();
        $keuangan->catatCaraBayarUangMuka();

        $keuangan->update([
            'tanggal_transfer' => $request->tanggal_transfer,
            'status' => 'bayar sebagian',
        ]);

        $dokKeuangan = $keuangan->dokumenKeuangan;
        if ($dokKeuangan) {
            if ($dokKeuangan->transfer_uang_muka) {
                Storage::disk('public')->delete($dokKeuangan->transfer_uang_muka);
            }
            $dokKeuangan->update(['transfer_uang_muka' => $path]);
        } else {
            $keuangan->dokumenKeuangan()->create([
                'transfer_uang_muka' => $path,
                'transfer_sisa' => '',
            ]);
        }

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_UANG_MUKA,
            (float) $keuangan->uang_muka,
            $request->tanggal_transfer,
            $request->user(),
            $path,
        );

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            'Uang muka sebesar Rp '.number_format($keuangan->uang_muka, 0, ',', '.')." dibayarkan untuk usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        $this->beritahuPengusul(
            $usulan,
            'Uang muka telah dibayarkan',
            'Uang muka perjalanan dinas Rp '.number_format($keuangan->uang_muka, 0, ',', '.').' sudah ditransfer.',
            Notifikasi::TIPE_SUKSES,
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Pembayaran uang muka berhasil dikonfirmasi.');
    }

    /**
     * Pelunasan menunggu laporan perjalanan dinasnya dikonfirmasi dan
     * ditandatangani pimpinan lewat QR — bukti bahwa hasil perjalanannya
     * sudah diterima, bukan hanya biayanya yang dihitung.
     *
     * Itulah satu-satunya syaratnya. Tanda tangan pelaksana dan PPK pada
     * rincian biaya, begitu pula daftar nominatifnya, boleh menyusul
     * sebelum maupun sesudah sisa dibayarkan: pembayaran tidak ditahan oleh
     * pengesahan yang masih berjalan.
     */
    private function laporanBelumDikonfirmasi(Usulan $usulan): ?string
    {
        $laporan = $usulan->laporan;

        if ($laporan?->sudahDikonfirmasi()) {
            return null;
        }

        if ($laporan?->sudahDikirim()) {
            return 'Laporan perjalanan dinas sudah dikirim pelaksana tetapi belum dikonfirmasi pimpinan, jadi pelunasan belum dapat dibayarkan.';
        }

        return 'Laporan perjalanan dinas belum ditandatangani pelaksana dan pimpinan lewat QR konfirmasi, jadi pelunasan belum dapat dibayarkan.';
    }

    public function bayarSisa(Request $request, Usulan $usulan)
    {
        $this->pastikanBolehMencatatPembayaran($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        $request->validate([
            'tanggal_pelunasan' => ['required', 'date'],
            'bukti_pelunasan' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $keuangan = $usulan->keuangan;

        if ($keuangan?->sudahLunas()) {
            return back()->with('error', 'Usulan ini sudah dilunasi pada '
                .$keuangan->tanggal_pelunasan->translatedFormat('d F Y')
                .'. Batalkan pelunasannya lebih dulu bila keliru.');
        }

        if ($alasan = $this->laporanBelumDikonfirmasi($usulan)) {
            return back()->with('error', $alasan);
        }

        $path = $request->file('bukti_pelunasan')->store('keuangan/pelunasan', 'public');

        $keuangan->update([
            'tanggal_pelunasan' => $request->tanggal_pelunasan,
            'status' => Keuangan::STATUS_LUNAS,
        ]);

        // Pelunasan menerbitkan kode konfirmasi bendahara, yang tercetak
        // sebagai QR pada dokumen rincian biaya.
        $keuangan->konfirmasiPelunasan();

        $dokKeuangan = $keuangan->dokumenKeuangan;
        if ($dokKeuangan) {
            if ($dokKeuangan->transfer_sisa) {
                Storage::disk('public')->delete($dokKeuangan->transfer_sisa);
            }
            $dokKeuangan->update(['transfer_sisa' => $path]);
        }

        $reimbursement = $keuangan->reimbursementTransport();
        $nilai = $keuangan->nilaiPelunasan();

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_PELUNASAN,
            $nilai,
            $request->tanggal_pelunasan,
            $request->user(),
            $path,
            'Sisa Rp '.number_format($keuangan->sisa, 0, ',', '.')
                .' + penggantian transport lokal Rp '.number_format($reimbursement, 0, ',', '.'),
        );

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            'Pelunasan Rp '.number_format($nilai, 0, ',', '.')." dibayarkan untuk usulan {$usulan->no_usulan}"
                .' (sisa Rp '.number_format($keuangan->sisa, 0, ',', '.')
                .' + reimbursement transport lokal Rp '.number_format($reimbursement, 0, ',', '.').').',
            ['usulan' => $usulan],
        );

        $this->beritahuPengusul(
            $usulan,
            'Sisa pembayaran telah dilunasi',
            'Pelunasan perjalanan dinas Rp '.number_format($nilai, 0, ',', '.').' sudah ditransfer: '
                .'sisa biaya Rp '.number_format($keuangan->sisa, 0, ',', '.')
                .' ditambah penggantian transport lokal Rp '.number_format($reimbursement, 0, ',', '.').'.',
            Notifikasi::TIPE_SUKSES,
        );

        // Cek apakah semua dokumen sudah lengkap → selesai
        if ($usulan->checkCompletion()) {
            $this->audit->catatPerubahanStatus(
                $usulan,
                AuditLog::AKSI_DISETUJUI,
                "Usulan {$usulan->no_usulan} dinyatakan selesai: pembayaran lunas dan dokumen pertanggungjawaban lengkap.",
                'disetujui',
            );

            $this->beritahuPengusul(
                $usulan,
                'Perjalanan dinas selesai',
                "Seluruh tahapan usulan {$usulan->no_usulan} telah selesai dan diarsipkan.",
                Notifikasi::TIPE_SUKSES,
            );

            return redirect()->route('keuangan.detail', $usulan->no_usulan)
                ->with('success', 'Pembayaran lunas & dokumen lengkap. Usulan telah selesai.');
        }

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Pembayaran sisa berhasil dikonfirmasi. Status: Lunas.');
    }

    /**
     * [Admin] Koreksi / reset status keuangan.
     */
    /**
     * Batalkan pembayaran uang muka yang keliru.
     *
     * Pembatalan tidak menghapus jejaknya: ia menambah satu baris pada jurnal
     * pembayaran, dan bukti transfer yang lama dibiarkan tersimpan supaya
     * riwayatnya tetap dapat ditelusuri. Yang dikosongkan hanya penunjuk
     * keadaan terakhirnya.
     */
    public function batalUangMuka(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanBolehMencatatPembayaran($request);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'alasan.required' => 'Jelaskan kenapa pembayaran ini dibatalkan.',
            'alasan.min' => 'Uraikan alasannya sedikit lebih rinci.',
        ]);

        $keuangan = $usulan->keuangan;

        abort_unless($keuangan?->uangMukaTerbayar(), 422, 'Uang muka usulan ini belum pernah dicatat.');
        abort_if($keuangan->sudahLunas(), 403, 'Usulan ini sudah lunas. Batalkan pelunasannya lebih dulu.');

        $nominal = (float) $keuangan->uang_muka;
        $tanggal = $keuangan->tanggal_transfer->toDateString();

        $keuangan->update(['tanggal_transfer' => null, 'status' => Keuangan::STATUS_BELUM]);
        $keuangan->dokumenKeuangan?->update(['transfer_uang_muka' => '']);

        // Uang muka yang batal ditransfer kembali dihitung dari cara bayar
        // tiap komponen.
        $keuangan->hitungTotal();

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_BATAL_UANG_MUKA,
            $nominal,
            $tanggal,
            $request->user(),
            null,
            $validated['alasan'],
        );

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            'Pembayaran uang muka Rp '.number_format($nominal, 0, ',', '.')." pada usulan {$usulan->no_usulan} dibatalkan.",
            ['usulan' => $usulan, 'catatan' => $validated['alasan']],
        );

        $this->beritahuPengusul(
            $usulan,
            'Pembayaran uang muka dibatalkan',
            'Pencatatan uang muka Rp '.number_format($nominal, 0, ',', '.').' dibatalkan: '.$validated['alasan'],
            Notifikasi::TIPE_BAHAYA,
        );

        return back()->with('success', 'Pembayaran uang muka dibatalkan dan tercatat pada riwayat.');
    }

    /**
     * Batalkan pelunasan yang keliru.
     *
     * Kode konfirmasi bendahara ikut dicabut: QR pada dokumen yang sudah
     * beredar tidak boleh lagi menyatakan berkas ini lunas.
     */
    public function batalPelunasan(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->pastikanBolehMencatatPembayaran($request);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'alasan.required' => 'Jelaskan kenapa pelunasan ini dibatalkan.',
            'alasan.min' => 'Uraikan alasannya sedikit lebih rinci.',
        ]);

        $keuangan = $usulan->keuangan;

        abort_unless($keuangan?->sudahLunas(), 422, 'Usulan ini belum dilunasi.');

        $nominal = $keuangan->nilaiPelunasan();
        $tanggal = $keuangan->tanggal_pelunasan->toDateString();

        $keuangan->update([
            'tanggal_pelunasan' => null,
            'status' => $keuangan->uangMukaTerbayar() ? Keuangan::STATUS_SEBAGIAN : Keuangan::STATUS_BELUM,
        ]);

        $keuangan->batalkanKonfirmasiPelunasan();
        $keuangan->dokumenKeuangan?->update(['transfer_sisa' => '']);

        RiwayatPembayaran::catat(
            $keuangan,
            RiwayatPembayaran::JENIS_BATAL_PELUNASAN,
            $nominal,
            $tanggal,
            $request->user(),
            null,
            $validated['alasan'],
        );

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            'Pelunasan Rp '.number_format($nominal, 0, ',', '.')." pada usulan {$usulan->no_usulan} dibatalkan.",
            ['usulan' => $usulan, 'catatan' => $validated['alasan']],
        );

        $this->beritahuPengusul(
            $usulan,
            'Pelunasan dibatalkan',
            'Pencatatan pelunasan Rp '.number_format($nominal, 0, ',', '.').' dibatalkan: '.$validated['alasan'],
            Notifikasi::TIPE_BAHAYA,
        );

        return back()->with('success', 'Pelunasan dibatalkan dan tercatat pada riwayat.');
    }

    public function koreksiStatus(Request $request, Usulan $usulan)
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $request->validate([
            'status_keuangan' => ['required', 'in:belum bayar,bayar sebagian,lunas'],
        ]);

        $keuangan = $usulan->keuangan;
        abort_unless($keuangan, 404, 'Data keuangan belum tersedia.');

        $newStatus = $request->input('status_keuangan');
        $updateData = ['status' => $newStatus];

        // Reset tanggal jika di-downgrade
        if ($newStatus === 'belum bayar') {
            $updateData['tanggal_transfer'] = null;
            $updateData['tanggal_pelunasan'] = null;
        } elseif ($newStatus === 'bayar sebagian') {
            $updateData['tanggal_pelunasan'] = null;
        }

        $statusKeuanganLama = $keuangan->status;
        $keuangan->update($updateData);

        // Konfirmasi bendahara hanya berlaku selama statusnya lunas; bila
        // diturunkan, QR pada dokumen yang beredar ikut gugur.
        if ($newStatus === Keuangan::STATUS_LUNAS) {
            $keuangan->konfirmasiPelunasan();
        } else {
            $keuangan->batalkanKonfirmasiPelunasan();
        }

        // Jika status usulan selesai tapi keuangan bukan lunas lagi, kembalikan ke disetujui
        if ($usulan->status === 'selesai' && $newStatus !== 'lunas') {
            $usulan->update(['status' => 'disetujui']);
        }

        $this->audit->catat(
            AuditLog::AKSI_PEMBAYARAN,
            "Status keuangan usulan {$usulan->no_usulan} dikoreksi administrator dari \"{$statusKeuanganLama}\" menjadi \"{$newStatus}\".",
            ['usulan' => $usulan],
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Status keuangan berhasil dikoreksi.');
    }

    /**
     * Input dan koreksi rincian biaya terbuka bagi tim keuangan dan bendahara.
     */
    /**
     * Tim keuangan menyatakan sebuah nominal dari berkas pelaksana sudah
     * benar. Sejak itu barisnya terhitung sah dan boleh dibayarkan.
     */
    public function validasiRincian(Request $request, Usulan $usulan, RincianBiaya $rincian)
    {
        $this->pastikanBolehMemvalidasi($request);
        $this->pastikanRincianMilikUsulan($usulan, $rincian);

        abort_unless($rincian->dariDokumen(), 422, 'Baris ini bukan berasal dari berkas pelaksana.');

        $rincian->update([
            'divalidasi_at' => now(),
            'id_validator' => $request->user()->id,
        ]);

        // Nominal yang sudah divalidasi baru terhitung pada total dan cetakan.
        $usulan->keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Nominal \"{$rincian->komponen}\" pada usulan {$usulan->no_usulan} divalidasi.",
            ['usulan' => $usulan],
        );

        // Bila ini pemeriksaan terakhir, berkas langsung berjalan ke pelaksana.
        if ($this->pengiriman->kirimBilaSiap($usulan->fresh())) {
            return back()->with('success', "Nominal \"{$rincian->komponen}\" divalidasi. Seluruh nominal sudah diperiksa, berkas dikirim ke pelaksana — masa sanggah ".DaftarRiil::HARI_MASA_SANGGAH.' hari.');
        }

        return back()->with('success', "Nominal \"{$rincian->komponen}\" divalidasi.");
    }

    /**
     * Cabut validasi bila ternyata angkanya masih perlu diperiksa lagi.
     */
    public function batalValidasiRincian(Request $request, Usulan $usulan, RincianBiaya $rincian)
    {
        $this->pastikanBolehMemvalidasi($request);
        $this->pastikanRincianMilikUsulan($usulan, $rincian);

        $rincian->update(['divalidasi_at' => null, 'id_validator' => null]);
        $usulan->keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Validasi nominal \"{$rincian->komponen}\" pada usulan {$usulan->no_usulan} dicabut oleh {$request->user()->nama}.",
            ['usulan' => $usulan],
        );

        return back()->with('success', 'Validasi dicabut, nominalnya kembali menunggu pemeriksaan.');
    }

    /**
     * Aturan isian baris rincian biaya beserta pesannya.
     *
     * Baris transport tulisan tim keuangan wajib menyebut tiket pelaksana
     * yang diwakilinya — atau bukan tiket — supaya tiket yang sama tidak
     * dinominalkan dua kali.
     *
     * @return array{0: array<string, list<mixed>>, 1: array<string, string>}
     */
    private function aturanRincianKeuangan(Request $request, bool $dariBerkas = false): array
    {
        $transport = $request->input('kategori') === KategoriBiaya::Transport->value;

        return [
            [
                'kategori' => ['required', Rule::in(array_keys(KategoriBiaya::options()))],
                'komponen' => ['required', 'string', 'max:255'],
                'volume' => ['required', 'integer', 'min:1'],
                'satuan' => ['required', 'string', 'max:50'],
                'harga_satuan' => ['required', 'numeric', 'min:0'],
                'keterangan' => ['nullable', 'string', 'max:255'],
                'isian_pelaksana' => [
                    Rule::requiredIf($transport && ! $dariBerkas),
                    'nullable',
                    Rule::in(array_column(IsianBiaya::pilihanTransport(), 'value')),
                ],
            ],
            [
                'kategori.in' => 'Kategori biaya tidak dikenali.',
                'isian_pelaksana.required' => 'Pilih tiket pelaksana yang diwakili baris transport ini, atau nyatakan bukan tiket pelaksana.',
                'isian_pelaksana.in' => 'Pilihan tiket pelaksana tidak dikenali.',
            ],
        ];
    }

    private function pastikanRincianMilikUsulan(Usulan $usulan, RincianBiaya $rincian): void
    {
        abort_unless(
            $rincian->id_keuangan === $usulan->keuangan?->id,
            404,
            'Rincian biaya ini bukan milik usulan tersebut.',
        );
    }

    /**
     * Menyatakan nominal sudah benar adalah tugas tim keuangan. Bendahara
     * membayar berdasarkan angka itu, jadi ia tidak memvalidasinya sendiri.
     */
    private function pastikanBolehMemvalidasi(Request $request): void
    {
        abort_unless(
            $request->user()->bisaMemvalidasiBiaya(),
            403,
            'Validasi nominal biaya dikerjakan tim keuangan.'
        );
    }

    private function pastikanBolehMengelolaBiaya(Request $request): void
    {
        abort_unless(
            $request->user()->bisaMengelolaBiaya(),
            403,
            'Anda tidak memiliki kewenangan menginput rincian biaya perjalanan dinas.'
        );
    }

    /**
     * Pencatatan pembayaran beserta bukti transfernya khusus bendahara.
     */
    private function pastikanBolehMencatatPembayaran(Request $request): void
    {
        abort_unless(
            $request->user()->bisaMencatatPembayaran(),
            403,
            'Pencatatan bukti pembayaran hanya dapat dilakukan bendahara.'
        );
    }

    /**
     * Kirim notifikasi keuangan kepada pengusul.
     */
    private function beritahuPengusul(Usulan $usulan, string $judul, string $pesan, string $tipe): void
    {
        if (! $usulan->user) {
            return;
        }

        $this->notifikasi->kirim($usulan->user, $judul, $pesan, [
            'usulan' => $usulan,
            'tipe' => $tipe,
        ]);
    }
}
