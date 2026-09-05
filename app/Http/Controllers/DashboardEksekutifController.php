<?php

namespace App\Http\Controllers;

use App\Models\TahunAnggaran;
use App\Services\AntreanPeran;
use App\Services\AsistenAi;
use App\Services\RingkasanEksekutif;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ringkasan eksekutif realisasi anggaran dan pergerakan pegawai selama
 * satu tahun anggaran, untuk pimpinan dan pengelola keuangan.
 *
 * Angkanya dihitung RingkasanEksekutif, yang juga melayani API — supaya
 * aplikasi lain membaca angka yang sama dengan yang tampil di layar.
 */
class DashboardEksekutifController extends Controller
{
    public function __construct(
        private AsistenAi $asisten,
        private RingkasanEksekutif $ringkasan,
        private AntreanPeran $antrean,
    ) {}

    public function __invoke(Request $request): View
    {
        $tahun = (int) $request->input('tahun', $this->ringkasan->tahunBawaan());

        $realisasiPerKategori = $this->ringkasan->realisasiPerKategori($tahun);

        return view('dashboard-eksekutif.index', [
            'tahun' => $tahun,
            'tahunTersedia' => $this->ringkasan->tahunTersedia(),
            'tahunAnggaran' => TahunAnggaran::firstWhere('tahun', $tahun),
            'bulan' => RingkasanEksekutif::BULAN,
            'realisasiPerKategori' => $realisasiPerKategori,
            'realisasiPerBulan' => $this->ringkasan->jumlahkanPerBulan($realisasiPerKategori),
            'totalRealisasi' => $this->ringkasan->totalRealisasi($tahun),
            'pegawaiPerBulan' => $this->ringkasan->pegawaiPerBulan($tahun),
            'pegawaiPerUnit' => $this->ringkasan->pegawaiPerUnit($tahun),
            'akanBerangkat' => $this->ringkasan->akanBerangkat(),
            'belumMelapor' => $this->ringkasan->belumMelapor(),
            'sedangBerjalan' => $this->ringkasan->sedangBerjalan(),
            // Berkas yang menunggu tindakan peran ini, bukan angka tahunannya.
            'antrean' => $this->antrean->panel($request->user()),
            // Panel AI menyembunyikan dirinya bila kunci API belum dipasang.
            'aiAktif' => $this->asisten->tersedia(),
        ]);
    }
}
