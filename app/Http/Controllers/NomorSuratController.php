<?php

namespace App\Http\Controllers;

use App\Services\RegisterNomorSurat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Daftar nomor SPD dan surat tugas per bulan, untuk dicocokkan arsiparis
 * dengan buku agenda surat keluar.
 */
class NomorSuratController extends Controller
{
    public function __construct(private RegisterNomorSurat $register) {}

    public function index(Request $request): View
    {
        [$tahun, $bulan] = $this->periode($request);
        $search = $request->input('search');

        $baris = $this->register->baris($tahun, $bulan, $search);

        return view('audit-log.nomor-surat', [
            'perBulan' => $this->register->perBulan($baris),
            'jumlahSpd' => $baris->whereNotNull('no_spd')->count(),
            'jumlahSuratTugas' => $baris->pluck('no_tugas')->filter()->unique()->count(),
            'belumBertandaTangan' => $baris->where('sumber', RegisterNomorSurat::SUMBER_SPD)->whereNull('no_spd_ttd')->count(),
            'jumlahBaris' => $baris->count(),
            'tahun' => $tahun,
            'bulan' => $bulan,
            'search' => $search,
            'tahunTersedia' => $this->tahunTersedia($tahun),
        ]);
    }

    /**
     * Unduh register sebagai berkas Excel, mengikuti saringan halamannya.
     */
    public function ekspor(Request $request): Response
    {
        [$tahun, $bulan] = $this->periode($request);

        $baris = $this->register->baris($tahun, $bulan, $request->input('search'));
        $judul = $bulan
            ? Carbon::create($tahun, $bulan, 1)->translatedFormat('F Y')
            : 'Tahun '.$tahun;

        $berkas = $this->register->susunXlsx($baris, $judul)->keString();
        $nama = 'Register-Nomor-Surat-'.str_replace(' ', '-', $judul).'.xlsx';

        return response($berkas, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Content-Length' => (string) strlen($berkas),
        ]);
    }

    /**
     * Tahun dan bulan yang diminta; tahun berjalan bila tidak dipilih.
     *
     * @return array{0: int, 1: ?int}
     */
    private function periode(Request $request): array
    {
        $tahun = (int) $request->input('tahun') ?: (int) now()->year;
        $bulan = (int) $request->input('bulan');

        return [$tahun, $bulan >= 1 && $bulan <= 12 ? $bulan : null];
    }

    /**
     * @return list<int>
     */
    private function tahunTersedia(int $tahun): array
    {
        return collect($this->register->tahunTersedia())
            ->push($tahun, (int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
