<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman pemberitahuan bagi peran yang modulnya masih dikembangkan.
 *
 * Peran yang modulnya sudah tersedia tidak punya urusan di sini dan
 * langsung diantar ke dasbor.
 */
class ModulDalamPengembanganController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $pengguna = $request->user();

        if ($pengguna->peran->modulTersedia()) {
            return redirect()->route('dashboard');
        }

        return view('dalam-pengembangan', ['pengguna' => $pengguna]);
    }
}
