<?php

namespace App\Http\Controllers;

use App\Enums\LevelPersetujuan;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\Notifikasi;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use App\Services\WorkflowUsulan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PersetujuanController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
        private WorkflowUsulan $workflow,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $tab = $request->input('tab', 'antrian');
        $pengguna = $request->user();

        $query = Usulan::with('user.unit', 'kegiatan', 'kategoriPerjadin')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('no_tugas', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhere('instansi', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('nama', 'like', "%{$search}%"))
                        ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status));

        // Tab "antrian" hanya menampilkan usulan yang benar-benar menunggu
        // keputusan pengguna ini; tab "semua" menampilkan riwayat lengkap.
        if ($tab === 'antrian') {
            $idAntrian = $this->workflow->antrianUntuk($pengguna)->pluck('id');
            $query->whereIn('id', $idAntrian);
        } elseif (! $pengguna->bisaMelihatSemuaUsulan()) {
            // Atasan langsung tanpa peran back office hanya melihat usulan bawahannya.
            $query->whereHas('user', fn ($q) => $q->where('id_atasan', $pengguna->id));
        }

        $usulan = $query->latest()->paginate(10)->withQueryString();

        $jumlahAntrian = $this->workflow->antrianUntuk($pengguna)->count();
        $statusOptions = StatusUsulan::options();

        return view('persetujuan.list-pemohon', compact(
            'usulan',
            'tab',
            'jumlahAntrian',
            'statusOptions',
        ));
    }

    public function show(Request $request, Usulan $usulan)
    {
        $usulan->load('user.unit', 'user.atasan', 'kegiatan', 'kategoriPerjadin', 'dokumen', 'peserta', 'riwayat.pelaku', 'persetujuan.approver');

        $levelBerjalan = $this->workflow->levelBerjalan($usulan);
        $bolehMemutuskan = $this->workflow->bolehMemutuskan($usulan, $request->user());
        $rantaiLevel = LevelPersetujuan::urutan();

        return view('persetujuan.detail-pemohon', compact(
            'usulan',
            'levelBerjalan',
            'bolehMemutuskan',
            'rantaiLevel',
        ));
    }

    public function export(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen', 'peserta');

        $pdf = Pdf::loadView('persetujuan.export-usulan', compact('usulan'));

        return $pdf->download("Usulan_{$usulan->no_usulan}.pdf");
    }

    public function dokumen($path)
    {
        $filePath = storage_path('app/public/'.$path);

        if (! file_exists($filePath)) {
            abort(404);
        }

        return response()->file($filePath);
    }

    public function setuju(Request $request, Usulan $usulan)
    {
        $this->pastikanBerwenang($request, $usulan);

        $request->validate(['catatan' => ['nullable', 'string', 'max:500']]);

        $this->workflow->setujui($usulan, $request->user(), $request->input('catatan'));

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)
            ->with('success', 'Keputusan persetujuan berhasil disimpan.');
    }

    public function tolak(Request $request, Usulan $usulan)
    {
        $this->pastikanBerwenang($request, $usulan);

        $request->validate(['catatan' => ['required', 'string', 'max:500']], [
            'catatan.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $this->workflow->tolak($usulan, $request->user(), $request->input('catatan'));

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)
            ->with('success', 'Usulan berhasil ditolak.');
    }

    /**
     * Kembalikan usulan kepada pengusul untuk diperbaiki.
     */
    public function revisi(Request $request, Usulan $usulan)
    {
        $this->pastikanBerwenang($request, $usulan);

        $request->validate(['catatan' => ['required', 'string', 'max:500']], [
            'catatan.required' => 'Catatan revisi wajib diisi agar pengusul tahu yang harus diperbaiki.',
        ]);

        $this->workflow->mintaRevisi($usulan, $request->user(), $request->input('catatan'));

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)
            ->with('success', 'Usulan dikembalikan kepada pengusul untuk diperbaiki.');
    }

    /**
     * [Admin] Kembalikan usulan ke awal rantai persetujuan.
     */
    public function batalkan(Request $request, Usulan $usulan)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya administrator yang dapat membatalkan keputusan.');

        $statusLama = $usulan->status;

        $this->workflow->ajukan($usulan);

        $this->audit->catatPerubahanStatus(
            $usulan,
            AuditLog::AKSI_DIBATALKAN,
            "Keputusan persetujuan usulan {$usulan->no_usulan} dibatalkan administrator dan diulang dari tahap awal.",
            $statusLama,
        );

        if ($usulan->user) {
            $this->notifikasi->kirim(
                $usulan->user,
                'Proses persetujuan diulang',
                "Usulan {$usulan->no_usulan} dikembalikan ke awal rantai persetujuan oleh administrator.",
                ['usulan' => $usulan, 'tipe' => Notifikasi::TIPE_PERINGATAN],
            );
        }

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)
            ->with('success', 'Proses persetujuan diulang dari tahap awal.');
    }

    /**
     * Pastikan pengguna berwenang memutuskan usulan pada tahapnya saat ini.
     */
    private function pastikanBerwenang(Request $request, Usulan $usulan): void
    {
        abort_unless(
            $usulan->sedangMenunggu(),
            403,
            'Usulan ini tidak sedang menunggu keputusan.'
        );

        abort_unless(
            $this->workflow->bolehMemutuskan($usulan, $request->user()),
            403,
            'Tahap ini bukan kewenangan Anda.'
        );
    }
}
