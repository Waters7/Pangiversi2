<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DaftarRiil;
use App\Models\Notifikasi;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\JalurPersetujuan;
use App\Services\KertasCetak;
use App\Services\NotifikasiService;
use App\Services\PengirimanBerkas;
use App\Services\PenguncianBerkas;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DaftarRiilController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
        private QrCodeService $qrCode,
        private PenguncianBerkas $kunci,
        private PengirimanBerkas $pengiriman,
    ) {}

    public function show(Usulan $usulan): View
    {
        $usulan->load(['user.unit', 'peserta', 'daftarRiil.ppk', 'keuangan.rincianBiaya']);

        // Setiap peserta selalu punya baris daftar riil agar siap diisi.
        $daftar = $usulan->peserta->map(
            fn (PesertaUsulan $peserta) => $usulan->daftarRiil->firstWhere('id_peserta', $peserta->id)
                ?? new DaftarRiil(['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id, 'total_riil' => 0])
        );

        // Rincian biaya tidak lagi ditampilkan berdampingan: ia dokumen yang
        // berbeda, bermenu sendiri di Persetujuan, dan angkanya tidak
        // sebanding dengan daftar riil yang hanya memuat transport lokal.
        return view('daftar-riil.show', [
            'usulan' => $usulan,
            'daftar' => $daftar,
        ]);
    }

    /**
     * Simpan nominal pengeluaran riil seorang peserta.
     */
    public function simpan(Request $request, Usulan $usulan, PesertaUsulan $peserta): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $validated = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $daftar = $this->daftarUntuk($usulan, $peserta);

        $this->kunci->pastikanRiilTerbuka($usulan);

        // Totalnya tidak lagi diketik lepas — ia selalu jumlah baris rinciannya,
        // supaya angka pada dokumen tidak pernah berbeda dari yang dirinci.
        $daftar->fill($validated)->save();
        $daftar->hitungTotal();

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Daftar pengeluaran riil {$peserta->nama} pada usulan {$usulan->no_usulan} diperbarui.",
            ['usulan' => $usulan],
        );

        return back()->with('success', "Daftar pengeluaran riil {$peserta->nama} berhasil disimpan.");
    }

    /**
     * Tim keuangan mengirimkan berkas pertanggungjawaban kepada pelaksana.
     *
     * Yang dikirim sepasang: rincian biaya perjalanan dinas (Lampiran II)
     * dan daftar pengeluaran riil (Lampiran IX). Keduanya berbagi satu masa
     * sanggah dan satu tanda tangan, sebab pelaksana memeriksanya sekaligus.
     */
    public function kirimKePegawai(Request $request, Usulan $usulan, PesertaUsulan $peserta): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $daftar = $this->daftarUntuk($usulan, $peserta);

        // Mengirim ulang mencabut sikap pelaksana atas kedua dokumen, jadi
        // ia tertutup begitu salah satunya disahkan PPK.
        abort_if(
            $daftar->sudah_ditandatangani || $daftar->jalurRincian()->sudahDitandatangani(),
            403,
            'Berkas sudah ditandatangani PPK. Mintalah PPK mencabut tanda tangannya lebih dulu.'
        );

        if ($alasan = $this->pengiriman->alasanBelumSiap($usulan, $daftar)) {
            return back()->with('error', $alasan);
        }

        $this->pengiriman->kirim($usulan, $peserta, $daftar);

        return back()->with('success', "Berkas dikirim ke {$peserta->nama}. Masa sanggah ".DaftarRiil::HARI_MASA_SANGGAH.' hari.');
    }

    /**
     * Tim keuangan memeriksa nominal transport lokal.
     *
     * Sama seperti baris rincian biaya, angkanya datang dari nota yang
     * diunggah pelaksana — harus diperiksa sebelum berjalan ke pelaksana
     * lalu ke PPK.
     */
    public function validasi(Request $request, Usulan $usulan, PesertaUsulan $peserta): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $daftar = $this->daftarUntuk($usulan, $peserta);

        abort_if($daftar->sudah_ditandatangani, 403, 'Berkas sudah ditandatangani PPK.');

        if ($daftar->total_riil <= 0) {
            return back()->with('error', 'Belum ada nominal transport lokal yang dapat divalidasi.');
        }

        $daftar->update(['divalidasi_at' => now(), 'id_validator' => $request->user()->id]);

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            'Transport lokal Rp '.number_format($daftar->total_riil, 0, ',', '.')
                ." pada usulan {$usulan->no_usulan} divalidasi.",
            ['usulan' => $usulan],
        );

        // Bila ini pemeriksaan terakhir, berkas langsung berjalan ke pelaksana.
        if ($this->pengiriman->kirimBilaSiap($usulan->fresh())) {
            return back()->with('success', "Transport lokal divalidasi. Seluruh nominal sudah diperiksa, berkas dikirim ke {$peserta->nama} — masa sanggah ".DaftarRiil::HARI_MASA_SANGGAH.' hari.');
        }

        return back()->with('success', 'Transport lokal divalidasi.');
    }

    public function batalValidasi(Request $request, Usulan $usulan, PesertaUsulan $peserta): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $daftar = $this->daftarUntuk($usulan, $peserta);

        abort_if($daftar->sudah_ditandatangani, 403, 'Berkas sudah ditandatangani PPK.');

        $daftar->update(['divalidasi_at' => null, 'id_validator' => null]);

        // Dicatat supaya dapat ditelusuri: pencabutan menahan berkas ke pelaksana,
        // dan tanpa jejak ini tidak ada yang tahu mengapa berkas tidak berjalan.
        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "Validasi transport lokal pada usulan {$usulan->no_usulan} dicabut oleh {$request->user()->nama}.",
            ['usulan' => $usulan],
        );

        return back()->with('success', 'Validasi dicabut, nominalnya kembali menunggu pemeriksaan.');
    }

    /**
     * PPK mengembalikan berkas kepada tim keuangan alih-alih
     * menandatanganinya.
     *
     * Alasannya wajib: tanpa itu tim keuangan hanya tahu berkasnya kembali,
     * bukan apa yang harus diperbaiki.
     */
    public function kembalikan(Request $request, Usulan $usulan, PesertaUsulan $peserta, ?string $jenis = null): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $validated = $request->validate([
            'alasan_kembali' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'alasan_kembali.required' => 'Jelaskan bagian mana yang perlu diperbaiki tim keuangan.',
            'alasan_kembali.min' => 'Uraikan alasannya sedikit lebih rinci.',
        ]);

        $daftar = $this->daftarUntuk($usulan, $peserta);
        $jalur = $daftar->jalur(JalurPersetujuan::kenali($jenis));

        abort_if($jalur->sudahDitandatangani(), 403, 'Berkas sudah ditandatangani dan tidak dapat dikembalikan.');

        $daftar->kembalikanKeKeuangan($validated['alasan_kembali'], $request->user());

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "Berkas pertanggungjawaban {$peserta->nama} pada usulan {$usulan->no_usulan} dikembalikan PPK ke tim keuangan.",
            ['usulan' => $usulan, 'catatan' => $validated['alasan_kembali']],
        );

        $this->notifikasi->kirimKePeran(
            [User::ROLE_TIM_KEUANGAN, User::ROLE_BENDAHARA],
            'Berkas dikembalikan PPK',
            "Berkas {$usulan->no_usulan} dikembalikan PPK: {$validated['alasan_kembali']}",
            [
                'usulan' => $usulan,
                'tipe' => Notifikasi::TIPE_BAHAYA,
                'url' => route('keuangan.detail', $usulan->no_usulan),
            ],
        );

        return back()->with('success', 'Berkas dikembalikan ke tim keuangan dengan catatan Anda.');
    }

    /**
     * Jumlah rincian biaya tanpa transport lokal — transport lokal
     * dipertanggungjawabkan lewat daftar riil.
     */
    /**
     * Pelaksana menyetujui nominal yang disusun tim keuangan.
     */
    public function setujuiPegawai(Request $request, Usulan $usulan, PesertaUsulan $peserta, ?string $jenis = null): RedirectResponse
    {
        $daftar = $this->pastikanPelaksanaSendiri($request, $usulan, $peserta);
        $jalur = $daftar->jalur(JalurPersetujuan::kenali($jenis));

        abort_unless(
            $jalur->bolehDitandatanganiPelaksana(),
            403,
            $jalur->sudahDitandatangani()
                ? "{$jalur->nama()} sudah ditandatangani PPK."
                : ($jalur->sudahDisetujui()
                    ? "Anda sudah menandatangani {$jalur->nama()}."
                    : 'Dokumen ini belum dapat ditandatangani.')
        );

        $jalur->setujui();

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "{$peserta->nama} menandatangani {$jalur->nama()} pada usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        // Setelah pelaksana menandatangani, kedua dokumen berjalan ke PPK.
        // Tim keuangan ikut diberi tahu karena merekalah yang menyiapkannya.
        $this->notifikasi->kirimKePeran(
            [User::ROLE_PPK],
            'Berkas pertanggungjawaban menunggu tanda tangan Anda',
            "{$peserta->nama} sudah menandatangani {$jalur->nama()} usulan {$usulan->no_usulan}. "
                .'Dokumen itu menunggu tanda tangan Anda.',
            [
                'usulan' => $usulan,
                'tipe' => Notifikasi::TIPE_PERINGATAN,
                'url' => $jalur->jenis() === JalurPersetujuan::RINCIAN
                    ? route('persetujuan.rincian-biaya')
                    : route('persetujuan.daftar-riil'),
            ],
        );

        $this->beritahuPengelolaBiaya(
            $usulan,
            'Berkas ditandatangani pelaksana',
            "{$peserta->nama} menandatangani {$jalur->nama()} usulan {$usulan->no_usulan}. Menunggu tanda tangan PPK.",
            Notifikasi::TIPE_SUKSES,
        );

        return back()->with('success', "Terima kasih, tanda tangan Anda pada {$jalur->nama()} tercatat.");
    }

    /**
     * Pelaksana menyanggah karena nominalnya dianggap tidak sesuai.
     */
    public function sanggah(Request $request, Usulan $usulan, PesertaUsulan $peserta, ?string $jenis = null): RedirectResponse
    {
        $daftar = $this->pastikanPelaksanaSendiri($request, $usulan, $peserta);
        $jalur = $daftar->jalur(JalurPersetujuan::kenali($jenis));

        // Sanggahan hanya selama masa sanggah berjalan — berbeda dari tanda
        // tangan, yang tetap terbuka sampai PPK mengesahkan.
        abort_unless(
            $jalur->masaSanggahBerjalan(),
            403,
            $jalur->sudahDitandatangani()
                ? "{$jalur->nama()} sudah ditandatangani PPK."
                : ($jalur->sudahDisetujui()
                    ? "Anda sudah menandatangani {$jalur->nama()}."
                    : 'Masa sanggah dokumen ini sudah berakhir.')
        );

        $validated = $request->validate([
            'sanggahan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'sanggahan.required' => 'Jelaskan bagian nominal yang Anda anggap tidak sesuai.',
            'sanggahan.min' => 'Uraikan sanggahan Anda sedikit lebih rinci.',
        ]);

        $jalur->sanggah($validated['sanggahan']);

        $this->audit->catat(
            AuditLog::AKSI_BIAYA,
            "{$peserta->nama} menyanggah {$jalur->nama()} pada usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan, 'catatan' => $validated['sanggahan']],
        );

        $this->beritahuPengelolaBiaya(
            $usulan,
            'Rincian biaya disanggah',
            "{$peserta->nama} menyanggah {$jalur->nama()} usulan {$usulan->no_usulan}: {$validated['sanggahan']}",
            Notifikasi::TIPE_BAHAYA,
        );

        return back()->with('success', "Sanggahan Anda atas {$jalur->nama()} terkirim ke tim keuangan.");
    }

    public function tandaTangani(Request $request, Usulan $usulan, PesertaUsulan $peserta, ?string $jenis = null): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $daftar = $this->daftarUntuk($usulan, $peserta);
        $jalur = $daftar->jalur(JalurPersetujuan::kenali($jenis));

        abort_if($jalur->total() <= 0, 422, "Nominal pada {$jalur->nama()} belum diisi.");

        abort_unless(
            $jalur->siapDitandatanganiPpk(),
            403,
            $jalur->sedangDisanggah()
                ? 'Sanggahan pelaksana belum diselesaikan tim keuangan.'
                : 'Pelaksana belum menyetujui rincian dan masa sanggah masih berjalan.'
        );

        $jalur->tandaTangani($request->user());

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "{$jalur->nama()} {$peserta->nama} pada usulan {$usulan->no_usulan} ditandatangani {$request->user()->nama}.",
            ['usulan' => $usulan],
        );

        if ($peserta->user) {
            $this->notifikasi->kirim(
                $peserta->user,
                "{$jalur->nama()} ditandatangani",
                "{$jalur->nama()} Anda pada usulan {$usulan->no_usulan} telah ditandatangani PPK.",
                [
                    'usulan' => $usulan,
                    'tipe' => Notifikasi::TIPE_SUKSES,
                    'url' => route('rincian-saya.daftar-riil'),
                ],
            );
        }

        return back()->with('success', "{$jalur->nama()} {$peserta->nama} berhasil ditandatangani.");
    }

    public function batalTandaTangan(Request $request, Usulan $usulan, PesertaUsulan $peserta, ?string $jenis = null): RedirectResponse
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $jalur = $this->daftarUntuk($usulan, $peserta)->jalur(JalurPersetujuan::kenali($jenis));
        $jalur->batalkanTandaTangan();

        $this->audit->catat(
            AuditLog::AKSI_DOKUMEN,
            "Tanda tangan {$jalur->nama()} {$peserta->nama} pada usulan {$usulan->no_usulan} dibatalkan.",
            ['usulan' => $usulan],
        );

        return back()->with('success', 'Tanda tangan berhasil dibatalkan.');
    }

    public function cetak(Usulan $usulan, PesertaUsulan $peserta): Response
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        $usulan->load('user.unit', 'kegiatan');

        // Yang dicetak hanya baris daftar riilnya sendiri — transport lokal.
        $daftar = $this->daftarUntuk($usulan, $peserta)->load('ppk', 'rincian');

        // Tiap tanda tangan punya QR sendiri, dan hanya dicetak setelah
        // pemiliknya benar-benar membubuhkannya.
        $qr = $daftar->urlVerifikasi()
            ? $this->qrCode->dataUri($daftar->urlVerifikasi())
            : null;

        $qrPelaksana = $daftar->urlKonfirmasi()
            ? $this->qrCode->dataUri($daftar->urlKonfirmasi())
            : null;

        $pdf = Pdf::loadView('daftar-riil.cetak', compact(
            'usulan', 'peserta', 'daftar', 'qr', 'qrPelaksana',
        ))->setPaper(KertasCetak::UKURAN);

        return $pdf->download("Daftar-Riil_{$usulan->no_usulan}_{$peserta->nama}.pdf");
    }

    private function daftarUntuk(Usulan $usulan, PesertaUsulan $peserta): DaftarRiil
    {
        return DaftarRiil::firstOrCreate(
            ['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id],
            ['total_riil' => 0],
        );
    }

    private function pastikanPesertaMilikUsulan(Usulan $usulan, PesertaUsulan $peserta): void
    {
        abort_if($peserta->id_usulan !== $usulan->id, 404);
    }

    /**
     * Hanya pelaksana yang bersangkutan yang boleh menyetujui atau menyanggah,
     * dan hanya selama masa sanggah masih berjalan.
     */
    private function pastikanPelaksanaSendiri(Request $request, Usulan $usulan, PesertaUsulan $peserta): DaftarRiil
    {
        $this->pastikanPesertaMilikUsulan($usulan, $peserta);

        abort_unless(
            $peserta->id_user === $request->user()->id,
            403,
            'Hanya pelaksana perjalanan yang dapat menanggapi rincian ini.'
        );

        $daftar = $this->daftarUntuk($usulan, $peserta);

        abort_unless($daftar->sudahDikirimKePegawai(), 403, 'Berkas belum dikirim tim keuangan.');

        return $daftar;
    }

    /**
     * Beri tahu pihak yang menyusun biaya bahwa ada tanggapan pelaksana.
     */
    private function beritahuPengelolaBiaya(Usulan $usulan, string $judul, string $pesan, string $tipe): void
    {
        $this->notifikasi->kirimKePeran(
            [User::ROLE_TIM_KEUANGAN, User::ROLE_BENDAHARA, User::ROLE_PPK],
            $judul,
            $pesan,
            [
                'usulan' => $usulan,
                'tipe' => $tipe,
                'url' => route('daftar-riil.show', $usulan->no_usulan),
            ],
        );
    }
}
