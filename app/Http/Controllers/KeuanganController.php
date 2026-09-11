<?php

namespace App\Http\Controllers;

use App\Enums\KategoriBiaya;
use App\Models\AuditLog;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\KomponenBiaya;
use App\Models\Notifikasi;
use App\Models\RincianBiaya;
use App\Models\RiwayatPembayaran;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\KertasCetak;
use App\Services\NotifikasiService;
use App\Services\PemberitahuanBendahara;
use App\Services\PenagihDokumen;
use App\Services\PengirimanBerkas;
use App\Services\PenguncianBerkas;
use App\Services\QrCodeService;
use App\Services\SinkronBiayaDokumen;
use App\Services\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

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

        $jumlahStatus = [
            'berjalan' => (clone $dasar())->where('status', 'disetujui')->count(),
            'selesai' => (clone $dasar())->where('status', 'selesai')->count(),
        ];

        $usulan = $dasar()
            ->with('user', 'kegiatan', 'kategoriPerjadin', 'keuangan', 'dokumen')
            ->when($status === 'berjalan', fn ($q) => $q->where('status', 'disetujui'))
            ->when($status === 'selesai', fn ($q) => $q->where('status', 'selesai'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Tautan penagihan berkas disiapkan di sini agar Blade tidak perlu
        // menghitung ulang kelengkapan dokumen tiap baris.
        $usulan->getCollection()->each(function (Usulan $item): void {
            $item->berkas_kurang = $this->penagih->berkasKurang($item);
            $item->tautan_wa = $this->penagih->tautanWhatsapp($item);
            $item->alasan_tanpa_wa = $this->penagih->alasanTidakTersedia($item);
        });

        return view('keuangan.keuangan', [
            'usulan' => $usulan,
            'status' => $status,
            'jumlahStatus' => $jumlahStatus,
        ]);
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

        // Standar biaya dipakai untuk mengisi otomatis satuan dan harga di form rincian.
        $komponenBiaya = KomponenBiaya::aktif()->orderBy('nama')->get();
        $kategoriBiaya = KategoriBiaya::options();

        if ($usulan->keuangan->status === 'lunas') {
            return view('keuangan.paid', compact('usulan', 'komponenBiaya', 'kategoriBiaya'));
        }

        if ($usulan->keuangan->status === 'bayar sebagian') {
            return view('keuangan.partial-paid', compact('usulan', 'komponenBiaya', 'kategoriBiaya'));
        }

        return view('keuangan.unpaid', compact('usulan', 'komponenBiaya', 'kategoriBiaya'));
    }

    /**
     * Cetak rincian biaya sesuai format Lampiran II PMK 113/PMK.05/2012.
     */
    public function cetakRincian(Request $request, Usulan $usulan): Response
    {
        $usulan->load('user.unit', 'kegiatan', 'keuangan.rincianBiaya', 'peserta');

        // Baris rincian resmi. Transport lokal tidak tercatat sebagai baris di
        // sini — ia dipertanggungjawabkan lewat Daftar Pengeluaran Riil — jadi
        // disaring tegas agar baris warisan atau salah ketik tidak menyelinap;
        // ia dicantumkan di bawah lewat daftar riilnya sendiri.
        $rincian = ($usulan->keuangan?->rincianBiaya ?? collect())
            ->reject(fn (RincianBiaya $item) => $item->kategori === KategoriBiaya::TransportLokal)
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

        // Transport lokal ikut tercetak sebagai kelompoknya sendiri, diambil
        // dari daftar riil peserta ini apa pun tahapnya: dokumen rincian biaya
        // harus memuat seluruh biaya perjalanan, dan transport lokal bagian
        // darinya walau dibayarkan terpisah saat pelunasan.
        $riilTransport = $peserta
            ? DaftarRiil::with('rincian')->where('id_peserta', $peserta->id)->where('id_usulan', $usulan->id)->first()
            : null;
        $transportLokal = $riilTransport?->rincian ?? collect();
        $totalTransportLokal = (float) ($riilTransport?->total_riil ?? $transportLokal->sum('nominal'));
        $totalKeseluruhan = $total + $totalTransportLokal;

        $keuangan = $usulan->keuangan;

        // Yang sudah dibayarkan mengikuti apa yang benar-benar keluar: uang
        // muka, sisa saat pelunasan, dan transport lokal bila sudah diganti —
        // entah lewat pelunasan atau lewat pembayaran transport tersendiri.
        $dibayarkan = (float) ($keuangan?->uang_muka ?? 0)
            + ($keuangan?->tanggal_pelunasan ? (float) $keuangan->sisa : 0)
            + ($riilTransport?->sudahDibayar() ? $totalTransportLokal : 0);

        $pdf = Pdf::loadView('keuangan.cetak-rincian', [
            'usulan' => $usulan,
            'peserta' => $peserta,
            'rincianPerKategori' => $rincianPerKategori,
            'total' => $total,
            'transportLokal' => $transportLokal,
            // Tanggal pelaksana menyetujui rincian ini — tercetak di atas tanda
            // tangannya, dari daftar riil pada tahap mana pun.
            'tanggalPelaksana' => $riilTransport?->rincian_disetujui_at ?? $riilTransport?->disetujui_pegawai_at,
            'totalTransportLokal' => $totalTransportLokal,
            'totalKeseluruhan' => $totalKeseluruhan,
            'dibayarkan' => $dibayarkan,
            'terbilang' => $this->terbilang->konversi($totalKeseluruhan),
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
        ])->setPaper(KertasCetak::UKURAN);

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

        $request->validate([
            'kategori' => ['required', Rule::in(array_keys(KategoriBiaya::options()))],
            'komponen' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'max:50'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $keuangan = $usulan->keuangan;

        $keuangan->rincianBiaya()->create([
            'kategori' => $request->kategori,
            'komponen' => $request->komponen,
            'volume' => $request->volume,
            'satuan' => $request->satuan,
            'harga_satuan' => $request->harga_satuan,
            'jumlah' => $request->volume * $request->harga_satuan,
            'keterangan' => $request->keterangan,
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

        $request->validate([
            'kategori' => ['required', Rule::in(array_keys(KategoriBiaya::options()))],
            'komponen' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'integer', 'min:1'],
            'satuan' => ['required', 'string', 'max:50'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $rincian->update([
            'kategori' => $request->kategori,
            'komponen' => $request->komponen,
            'volume' => $request->volume,
            'satuan' => $request->satuan,
            'harga_satuan' => $request->harga_satuan,
            'jumlah' => $request->volume * $request->harga_satuan,
            'keterangan' => $request->keterangan,
        ]);

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
     * Hapus rincian biaya.
     */
    public function destroyRincian(Request $request, Usulan $usulan, RincianBiaya $rincian)
    {
        $this->pastikanBolehMengelolaBiaya($request);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        // Angka yang sudah ditandatangani tidak boleh bergeser: dokumen
        // tercetak dan daftar nominatif menumpang di atasnya.
        $this->kunci->pastikanRincianTerbuka($usulan);

        $komponen = $rincian->komponen;
        $rincian->delete();
        $usulan->keuangan->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Komponen biaya \"{$komponen}\" dihapus dari usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        return redirect()->route('keuangan.detail', $usulan->no_usulan)
            ->with('success', 'Rincian biaya berhasil dihapus.');
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

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Validasi nominal \"{$rincian->komponen}\" pada usulan {$usulan->no_usulan} dicabut oleh {$request->user()->nama}.",
            ['usulan' => $usulan],
        );

        return back()->with('success', 'Validasi dicabut, nominalnya kembali menunggu pemeriksaan.');
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
