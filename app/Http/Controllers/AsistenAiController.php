<?php

namespace App\Http\Controllers;

use App\Models\TahunAnggaran;
use App\Services\AsistenAi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Titik masuk fitur AI pada dashboard eksekutif.
 *
 * Keduanya hanya membaca data; tidak ada aksi yang mengubah keadaan aplikasi.
 */
class AsistenAiController extends Controller
{
    public function __construct(private AsistenAi $asisten) {}

    /**
     * Wawasan otomatis atas data satu tahun anggaran.
     */
    public function wawasan(Request $request): JsonResponse
    {
        $tahun = $this->tahun($request);

        return response()->json(
            $this->asisten->wawasan($tahun, $request->boolean('segarkan'))
        );
    }

    /**
     * Tanya-jawab dengan agen yang membaca data lewat alat aplikasi.
     */
    public function tanya(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:1000'],
            'riwayat' => ['array', 'max:20'],
            'riwayat.*.role' => ['required', 'in:user,assistant'],
            'riwayat.*.content' => ['required', 'string', 'max:8000'],
        ]);

        return response()->json(
            $this->asisten->tanya(
                $validated['pertanyaan'],
                $this->tahun($request),
                $validated['riwayat'] ?? [],
            )
        );
    }

    private function tahun(Request $request): int
    {
        return (int) $request->input('tahun', TahunAnggaran::aktif()?->tahun ?? now()->year);
    }
}
