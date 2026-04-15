<?php

namespace App\Http\Controllers;

use App\Models\Usulan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PersetujuanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $usulan = Usulan::with('user', 'kegiatan')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('no_usulan', 'like', "%{$search}%")
                        ->orWhere('no_tugas', 'like', "%{$search}%")
                        ->orWhere('lokasi', 'like', "%{$search}%")
                        ->orWhere('instansi', 'like', "%{$search}%")
                        ->orWhereHas('kegiatan', fn ($q) => $q->where('nama', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('persetujuan.list-pemohon', compact(
            'usulan',
        ));
    }

    public function show(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen');

        return view('persetujuan.detail-pemohon', compact('usulan'));
    }

    public function export(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen');

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

    public function setuju(Usulan $usulan)
    {
        abort_if($usulan->status === 'selesai' && ! request()->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        $usulan->update(['status' => 'disetujui']);

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)->with('success', 'Usulan berhasil disetujui.');
    }

    public function batalkan(Usulan $usulan)
    {
        abort_if($usulan->status === 'selesai' && ! request()->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        $previousStatus = $usulan->status;
        $usulan->update(['status' => 'diajukan']);
        $message = $previousStatus === 'disetujui' ? 'Usulan berhasil dibatalkan.' : 'Usulan berhasil diajukan kembali.';

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)->with('success', $message);
    }

    public function tolak(Request $request, Usulan $usulan)
    {
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai.');

        $request->validate([
            'catatan' => 'nullable|string|max:255',
        ]);

        $usulan->update([
            'status' => 'ditolak',
            'catatan' => $request->input('catatan') ?? null,
        ]);

        return redirect()->route('persetujuan.detail', $usulan->no_usulan)->with('success', 'Usulan berhasil ditolak.');

    }
}
