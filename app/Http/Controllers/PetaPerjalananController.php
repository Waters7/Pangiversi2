<?php

namespace App\Http\Controllers;

use App\Services\PemetaPerjalanan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Peta kota tujuan perjalanan dinas — submenu Jadwal Perjalanan, dipilah
 * menjadi perjalanan dalam kota & sekitarnya dan perjalanan luar kota.
 */
class PetaPerjalananController extends Controller
{
    public function __construct(private PemetaPerjalanan $pemeta) {}

    public function __invoke(Request $request, string $jenis): View
    {
        abort_unless(in_array($jenis, PemetaPerjalanan::JENIS, true), 404);

        $tahunTersedia = $this->pemeta->tahunTersedia();
        $tahun = $request->integer('tahun') ?: null;

        if ($tahun !== null && ! in_array($tahun, $tahunTersedia, true)) {
            $tahun = null;
        }

        $dalamKota = $jenis === PemetaPerjalanan::DALAM_KOTA;

        return view('jadwal-perjalanan.peta', [
            'jenis' => $jenis,
            'dalamKota' => $dalamKota,
            'judul' => $dalamKota ? 'Perjalanan Dalam Kota & Sekitarnya' : 'Perjalanan Luar Kota',
            'peta' => $this->pemeta->susun($jenis, $tahun),
            'tahun' => $tahun,
            'tahunTersedia' => $tahunTersedia,
        ]);
    }
}
