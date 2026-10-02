<?php

namespace App\Http\Controllers;

use App\Services\KabarPerjalanan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * News feed dashboard eksekutif: lini masa siapa saja yang akan dan sedang
 * melakukan perjalanan dinas, serta tindak lanjut yang dijadwalkan.
 */
class NewsFeedEksekutifController extends Controller
{
    public function __construct(private KabarPerjalanan $kabar) {}

    public function __invoke(Request $request): View
    {
        $hari = in_array((int) $request->input('rentang'), KabarPerjalanan::RENTANG, true)
            ? (int) $request->input('rentang')
            : KabarPerjalanan::RENTANG_BAWAAN;

        $jenis = in_array($request->input('jenis'), [KabarPerjalanan::JENIS_PERJALANAN, KabarPerjalanan::JENIS_TINDAK_LANJUT], true)
            ? $request->input('jenis')
            : null;

        $kabar = $this->kabar->kabar($hari, $jenis);

        return view('dashboard-eksekutif.news-feed', [
            // Urutan kelompok mengikuti urutan kabar: lewat tenggat, hari ini, besok, dst.
            'kelompok' => $kabar->groupBy('kelompok'),
            'jumlah' => $kabar->count(),
            'ringkasan' => $this->kabar->ringkasan($hari),
            'hari' => $hari,
            'jenis' => $jenis,
        ]);
    }
}
