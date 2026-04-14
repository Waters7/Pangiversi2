<?php

namespace App\Http\Controllers;

use App\Models\Usulan;
use App\Services\DokService;
use Illuminate\Http\Request;

class DokumenController extends Controller
{
    private DokService $dokService;

    public function __construct()
    {
        $this->dokService = new DokService;
    }

    public function index(Request $request)
    {
        $usulan = Usulan::with('user', 'kegiatan')->where('status', 'disetujui')->get();

        return view('dokumen.dokumen', compact('usulan'));
    }

    public function show(Usulan $usulan)
    {
        $usulan->load('user', 'kegiatan', 'dokumen');

        return view('dokumen.form-dok', compact('usulan'));
    }

    public function store(Request $request, Usulan $usulan)
    {
        $type = $request->input('section', '');

        $result = $this->dokService->store($request, $usulan, $type);

        if ($result) {
            return redirect()->route('dokumen.show', $usulan->no_usulan)->with('success', 'Dokumen berhasil diunggah.');
        } else {
            return redirect()->route('dokumen.show', $usulan->no_usulan)->with('error', 'Gagal mengunggah dokumen. Silakan coba lagi.');
        }

    }
}
