<?php

namespace App\Http\Controllers;

use App\Services\PustakaSuratTugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Surat tugas menurut nomornya: pemeriksaan dari formulir — apakah nomornya
 * sudah pernah diunggah — dan daftar seluruhnya pada Jejak Audit.
 */
class SuratTugasController extends Controller
{
    public function __construct(private PustakaSuratTugas $pustaka) {}

    /**
     * Tombol Cek pada isian nomor surat tugas. Bila nomornya sudah pernah
     * diunggah, berkas itulah yang dipakai — formulir tidak meminta unggahan.
     */
    public function cek(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'no_tugas' => ['required', 'string', 'max:255'],
            'kecuali_usulan' => ['nullable', 'integer'],
            'kecuali_spd' => ['nullable', 'integer'],
        ], [
            'no_tugas.required' => 'Isi nomor surat tugas lebih dulu.',
        ]);

        $asal = $this->pustaka->temukan(
            $validated['no_tugas'],
            $validated['kecuali_usulan'] ?? null,
            $validated['kecuali_spd'] ?? null,
        );

        if (! $asal) {
            return response()->json([
                'ditemukan' => false,
                'pesan' => 'Nomor surat tugas ini belum pernah diunggah. Unggah berkas surat tugasnya.',
            ]);
        }

        return response()->json([
            'ditemukan' => true,
            'no_tugas' => $asal['no_tugas'],
            'pengunggah' => $asal['pengunggah'],
            'diunggah' => $asal['waktu']?->translatedFormat('d F Y'),
            'jumlah' => $asal['jumlah'],
            'tautan' => $asal['tautan'],
            'nama_berkas' => basename($asal['path']),
            'pesan' => 'Surat tugas bernomor ini sudah diunggah'
                .($asal['pengunggah'] ? ' oleh '.$asal['pengunggah'] : '')
                .($asal['waktu'] ? ' pada '.$asal['waktu']->translatedFormat('d F Y') : '')
                .' — berkasnya dipakai otomatis, tidak perlu diunggah lagi.',
        ]);
    }

    /**
     * Jejak Audit → Surat Tugas: tiap nomor surat tugas beserta berkasnya,
     * siapa yang pertama mengunggah, dan usulan serta SPD yang memakainya.
     */
    public function index(Request $request): View
    {
        $cari = $request->input('search');
        $tahun = $request->integer('tahun') ?: null;

        $daftar = $this->pustaka->daftar($cari, $tahun);

        $halaman = LengthAwarePaginator::resolveCurrentPage();
        $perHalaman = 20;

        $suratTugas = new LengthAwarePaginator(
            $daftar->forPage($halaman, $perHalaman)->values(),
            $daftar->count(),
            $perHalaman,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $semua = $this->pustaka->daftar();

        return view('audit-log.surat-tugas', [
            'suratTugas' => $suratTugas,
            'cari' => $cari,
            'tahun' => $tahun,
            'tahunTersedia' => $semua->map(fn (array $baris) => $baris['pertama']?->year)->filter()->unique()->sortDesc()->values(),
            'jumlahNomor' => $daftar->count(),
            'jumlahBerdokumen' => $daftar->filter(fn (array $baris) => $baris['dokumen'] !== null)->count(),
            'jumlahDipakaiBersama' => $daftar->filter(fn (array $baris) => $baris['usulan']->count() + $baris['spd']->count() > 1)->count(),
        ]);
    }
}
