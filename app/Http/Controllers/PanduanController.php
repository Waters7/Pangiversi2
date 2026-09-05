<?php

namespace App\Http\Controllers;

use App\Models\DaftarRiil;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panduan penggunaan PANGI, disusun mengikuti urutan yang benar-benar
 * dilalui pengguna: mengajukan, menunggu validasi, berangkat, lalu
 * mempertanggungjawabkan.
 */
class PanduanController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('panduan.index', [
            'peran' => $request->user()->peran,
            'hariSanggah' => DaftarRiil::HARI_MASA_SANGGAH,
        ]);
    }
}
