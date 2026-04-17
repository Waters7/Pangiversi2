<?php

namespace App\Http\Controllers;

use App\Models\Usulan;
use App\Services\DokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DokumenController extends Controller
{
    private DokService $dokService;

    public function __construct()
    {
        $this->dokService = new DokService;
    }

    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();

        $usulan = Usulan::with('user', 'kegiatan')
            ->when(! $isAdmin, fn ($q) => $q->where('id_user', Auth::id()))
            ->whereIn('status', ['disetujui', 'selesai'])
            ->latest()
            ->get();

        return view('dokumen.dokumen', compact('usulan'));
    }

    public function show(Usulan $usulan)
    {
        abort_if($usulan->id_user !== Auth::id() && ! request()->user()->isAdmin(), 403);

        $usulan->load('user', 'kegiatan', 'dokumen');

        return view('dokumen.form-dok', compact('usulan'));
    }

    public function store(Request $request, Usulan $usulan)
    {
        abort_if($usulan->id_user !== Auth::id() && ! $request->user()->isAdmin(), 403);
        abort_if($usulan->status === 'selesai' && ! $request->user()->isAdmin(), 403, 'Usulan sudah selesai, dokumen tidak dapat diubah.');

        $type = $request->input('section', '');

        $result = $this->dokService->store($request, $usulan, $type);

        if ($result) {
            return redirect()->route('dokumen.show', $usulan->no_usulan)->with('success', 'Dokumen berhasil diunggah.');
        } else {
            return redirect()->route('dokumen.show', $usulan->no_usulan)->with('error', 'Gagal mengunggah dokumen. Silakan coba lagi.');
        }

    }
}
