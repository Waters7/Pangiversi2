<?php

namespace App\Http\Controllers;

use App\Enums\JenisPerjadin;
use App\Enums\Kemampuan;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\DokService;
use App\Services\FormatLaporanPerjadin;
use App\Services\KertasCetak;
use App\Services\NotifikasiService;
use App\Services\PemberitahuanBendahara;
use App\Services\PenagihDokumen;
use App\Services\PengingatDokumen;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class DokumenController extends Controller
{
    private const BARIS_PER_HALAMAN = 25;

    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
        private PemberitahuanBendahara $bendahara,
        private DokService $dokService,
        private PenagihDokumen $penagih,
        private PengingatDokumen $pengingat,
    ) {}

    public function index(Request $request)
    {
        $lihatSemua = $request->user()->punyaKemampuan(Kemampuan::MelihatSemuaDokumen);
        $bantuUnggah = $request->user()->punyaKemampuan(Kemampuan::MembantuUnggahDokumen);
        $cari = $request->input('cari');

        // Navigasi hanya bagi yang melihat seluruh perjalanan. Pelaksana cukup
        // mendapati perjalanannya sendiri, terurut per bulan.
        $jalur = $lihatSemua ? JenisPerjadin::dari($request->input('jalur')) : null;
        $daftarUnit = $lihatSemua ? UnitKerja::orderBy('nama')->get(['id', 'nama']) : collect();
        $unit = $daftarUnit->firstWhere('id', $request->integer('unit'))?->id;
        $tahun = $lihatSemua ? $request->input('tahun') : null;
        $bulan = $lihatSemua ? $request->input('bulan') : null;
        $kelengkapan = $lihatSemua && in_array($request->input('kelengkapan'), ['lengkap', 'belum'], true)
            ? $request->input('kelengkapan')
            : null;

        // Relasi yang dibaca penagih berkas ikut dimuat sekaligus, supaya kolom
        // kelengkapan tidak menanyai basis data per baris.
        $usulan = Usulan::with('user.unit', 'kegiatan', 'kategoriPerjadin', 'dokumen', 'tiket', 'notaTransport', 'laporan', 'keuangan.rincianBiaya')
            ->when(! $lihatSemua, fn ($q) => $q->where('id_user', Auth::id()))
            ->whereIn('status', ['disetujui', 'selesai'])
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('no_usulan', 'like', "%{$cari}%")
                ->orWhere('no_tugas', 'like', "%{$cari}%")
                ->orWhere('lokasi', 'like', "%{$cari}%")
                ->orWhereHas('user', fn ($u) => $u->where('nama', 'like', "%{$cari}%"))))
            ->when($jalur, fn ($q) => $q->padaJalur($jalur))
            ->when($unit, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('id_unit', $unit)))
            ->orderByDesc('tanggal_mulai')
            ->get();

        $bulanBerangkat = fn (Usulan $item) => Carbon::parse($item->tanggal_mulai);
        $tahunTersedia = $usulan->map(fn (Usulan $item) => $bulanBerangkat($item)->year)->unique()->sortDesc()->values();
        $padaTahun = $usulan->when($tahun, fn ($isi) => $isi->filter(fn (Usulan $item) => $bulanBerangkat($item)->year === (int) $tahun));
        $jumlahBulan = $padaTahun->countBy(fn (Usulan $item) => $bulanBerangkat($item)->month);

        $usulan = $padaTahun
            ->when($bulan, fn ($isi) => $isi->filter(fn (Usulan $item) => $bulanBerangkat($item)->month === (int) $bulan))
            ->values();

        // Berkas yang belum diunggah tiap perjalanan, untuk kolom statusnya.
        $berkasKurang = $usulan->mapWithKeys(fn (Usulan $item) => [$item->id => $this->penagih->berkasKurang($item)]);

        $usulan = $usulan
            ->when($kelengkapan, fn ($isi) => $isi->filter(
                fn (Usulan $item) => ($berkasKurang[$item->id] === []) === ($kelengkapan === 'lengkap')
            ))
            ->values();

        // Seluruh perjalanan dapat ribuan baris; perjalanan sendiri tidak.
        $halaman = null;

        if ($lihatSemua) {
            $nomorHalaman = max(1, $request->integer('page', 1));
            $halaman = new LengthAwarePaginator(
                $usulan->forPage($nomorHalaman, self::BARIS_PER_HALAMAN)->values(),
                $usulan->count(),
                self::BARIS_PER_HALAMAN,
                $nomorHalaman,
                ['path' => $request->url(), 'query' => $request->query()],
            );
            $usulan = $halaman->getCollection();
        }

        // Dikelompokkan menurut bulan keberangkatan, lalu menurut tanggalnya
        // di dalam tiap bulan — arsip dicari berdasarkan kapan perjalanannya,
        // bukan kapan barisnya dibuat.
        $perBulan = $usulan
            ->groupBy(fn (Usulan $item) => Carbon::parse($item->tanggal_mulai)->format('Y-m'))
            ->map(fn ($isi) => $isi->groupBy(
                fn (Usulan $item) => Carbon::parse($item->tanggal_mulai)->toDateString()
            ));

        // Peran yang melihat dokumen seluruh perjalanan — super administrator
        // dan yang diberi hak akses menu Dokumen — mendapati pelaksananya.
        return view('dokumen.dokumen', compact(
            'usulan', 'perBulan', 'cari', 'lihatSemua', 'bantuUnggah', 'berkasKurang', 'halaman',
            'jalur', 'unit', 'daftarUnit', 'tahun', 'bulan', 'kelengkapan', 'tahunTersedia', 'jumlahBulan',
        ));
    }

    public function show(Usulan $usulan)
    {
        abort_unless(request()->user()->bolehMelihatDokumen($usulan), 403);

        $usulan->load('user', 'kegiatan', 'kategoriPerjadin', 'dokumen', 'tiket', 'notaTransport', 'laporan');

        return view('dokumen.form-dok', [
            'usulan' => $usulan,
            'tiket' => $usulan->tiket->keyBy(fn ($t) => $t->arah->value),
            'nota' => $usulan->notaTransport->keyBy('urutan'),
            'totalNota' => (float) $usulan->notaTransport->sum('nominal'),
            'berkasKurang' => $this->penagih->berkasKurang($usulan),
            'tenggangLaporan' => $this->pengingat->tenggangHari(),
            'batasLaporan' => $this->pengingat->batasLaporan($usulan),
            'sisaHariLaporan' => $this->pengingat->sisaHari($usulan),
        ]);
    }

    /**
     * Unduh format baku Laporan Perjalanan Dinas, sudah terisi identitas dan
     * jadwal perjalanannya. Pegawai tinggal melengkapi uraian kegiatan dan
     * rencana tindak lanjut, menandatangani, lalu mengunggahnya kembali.
     */
    public function formatLaporan(Usulan $usulan, FormatLaporanPerjadin $format): Response
    {
        abort_unless(request()->user()->bolehMelihatDokumen($usulan), 403);

        $pdf = Pdf::loadView('dokumen.format-laporan', $format->data($usulan))
            ->setPaper(KertasCetak::UKURAN);

        return $pdf->download("Format-Laporan-Perjadin_{$usulan->no_usulan}.pdf");
    }

    public function store(Request $request, Usulan $usulan)
    {
        abort_unless($request->user()->bolehMengunggahDokumen($usulan), 403);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai, dokumen tidak dapat diubah.');

        // Berkas tetap dapat diunggah sesudah dokumen ditandatangani; nominal
        // yang sudah tercantum dijaga DokService supaya angkanya tidak bergeser.
        $type = $request->input('section', '');

        $result = $this->dokService->store($request, $usulan, $type);

        if ($result) {
            $label = $type !== '' ? str_replace('_', ' ', $type) : 'pendukung';

            // Diunggah orang lain atas nama pelaksananya: namanya ikut dicatat.
            $pembantu = $usulan->id_user !== $request->user()->id ? $request->user() : null;

            $this->audit->catat(
                AuditLog::AKSI_DOKUMEN,
                "Dokumen {$label} untuk usulan {$usulan->no_usulan} diunggah"
                    .($pembantu ? " oleh {$pembantu->nama} atas nama {$usulan->user?->nama}." : '.'),
                ['usulan' => $usulan],
            );

            // Pelaksana perlu tahu berkasnya diunggahkan, supaya ia memeriksanya
            // dan tidak mengunggah ulang.
            if ($pembantu && $usulan->user) {
                $this->notifikasi->kirim(
                    $usulan->user,
                    'Dokumen Anda diunggahkan',
                    "{$pembantu->nama} mengunggah dokumen {$label} untuk usulan {$usulan->no_usulan} atas nama Anda.",
                    [
                        'usulan' => $usulan,
                        'tipe' => Notifikasi::TIPE_INFO,
                        'url' => route('dokumen.show', $usulan->no_usulan),
                    ],
                );
            }

            // Bagian keuangan perlu tahu ada berkas pertanggungjawaban baru untuk diverifikasi.
            $this->notifikasi->kirimKePeran(
                [User::ROLE_BENDAHARA, User::ROLE_TIM_KEUANGAN, User::ROLE_PPK],
                'Dokumen pertanggungjawaban baru',
                "Dokumen {$label} untuk usulan {$usulan->no_usulan} telah diunggah dan menunggu verifikasi.",
                [
                    'usulan' => $usulan,
                    'tipe' => Notifikasi::TIPE_INFO,
                    'url' => route('keuangan.detail', $usulan->no_usulan),
                ],
            );

            // Begitu seluruh berkas lengkap, bendahara diberi tahu bahwa
            // pelunasan sisa 20% sudah dapat diproses.
            $this->bendahara->berkasLengkap($usulan->fresh('dokumen', 'keuangan', 'user'));

            return redirect()->route('dokumen.show', $usulan->no_usulan)->with('success', 'Dokumen berhasil diunggah.');
        } else {
            return redirect()->route('dokumen.show', $usulan->no_usulan)->with('error', 'Gagal mengunggah dokumen. Silakan coba lagi.');
        }

    }
}
