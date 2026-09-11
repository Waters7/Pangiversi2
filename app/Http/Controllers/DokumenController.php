<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Notifikasi;
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
use App\Services\PenguncianBerkas;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DokumenController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
        private PemberitahuanBendahara $bendahara,
        private DokService $dokService,
        private PenagihDokumen $penagih,
        private PengingatDokumen $pengingat,
        private PenguncianBerkas $kunci,
    ) {}

    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();
        $cari = $request->input('cari');

        $usulan = Usulan::with('user', 'kegiatan', 'kategoriPerjadin')
            ->when(! $isAdmin, fn ($q) => $q->where('id_user', Auth::id()))
            ->whereIn('status', ['disetujui', 'selesai'])
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w
                ->where('no_usulan', 'like', "%{$cari}%")
                ->orWhere('no_tugas', 'like', "%{$cari}%")
                ->orWhere('lokasi', 'like', "%{$cari}%")
                ->orWhereHas('user', fn ($u) => $u->where('nama', 'like', "%{$cari}%"))))
            ->orderByDesc('tanggal_mulai')
            ->get();

        // Dikelompokkan menurut bulan keberangkatan, lalu menurut tanggalnya
        // di dalam tiap bulan — arsip dicari berdasarkan kapan perjalanannya,
        // bukan kapan barisnya dibuat.
        $perBulan = $usulan
            ->groupBy(fn (Usulan $item) => Carbon::parse($item->tanggal_mulai)->format('Y-m'))
            ->map(fn ($isi) => $isi->groupBy(
                fn (Usulan $item) => Carbon::parse($item->tanggal_mulai)->toDateString()
            ));

        return view('dokumen.dokumen', compact('usulan', 'perBulan', 'cari'));
    }

    public function show(Usulan $usulan)
    {
        abort_if($usulan->id_user !== Auth::id() && ! request()->user()->isAdmin(), 403);

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
        abort_if($usulan->id_user !== Auth::id() && ! request()->user()->isAdmin(), 403);

        $pdf = Pdf::loadView('dokumen.format-laporan', $format->data($usulan))
            ->setPaper(KertasCetak::UKURAN);

        return $pdf->download("Format-Laporan-Perjadin_{$usulan->no_usulan}.pdf");
    }

    public function store(Request $request, Usulan $usulan)
    {
        abort_if($usulan->id_user !== Auth::id() && ! $request->user()->isAdmin(), 403);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai, dokumen tidak dapat diubah.');

        // Tiket, nota, dan bill hotel adalah sumber angka pada kedua
        // dokumen. Mengubahnya setelah ditandatangani akan menggeser
        // nilai yang sudah disetujui, jadi ditutup di sini.
        $this->kunci->pastikanUnggahanTerbuka($usulan);

        $type = $request->input('section', '');

        $result = $this->dokService->store($request, $usulan, $type);

        if ($result) {
            $label = $type !== '' ? str_replace('_', ' ', $type) : 'pendukung';

            $this->audit->catat(
                AuditLog::AKSI_DOKUMEN,
                "Dokumen {$label} untuk usulan {$usulan->no_usulan} diunggah.",
                ['usulan' => $usulan],
            );

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
