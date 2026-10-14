<?php

namespace App\Http\Controllers;

use App\Models\Usulan;
use App\Services\PustakaBerkasBersama;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tombol Cek pada dokumen pertanggungjawaban: apakah nomor tiket, nomor
 * transaksi hotel, atau nomor invoice penyelenggaraan sudah pernah diunggah
 * rekan serombongan. Bila sudah, berkasnya dipakai tanpa unggah ulang.
 */
class BerkasBersamaController extends Controller
{
    public function __invoke(Request $request, PustakaBerkasBersama $pustaka): JsonResponse
    {
        $validated = $request->validate([
            'jenis' => ['required', Rule::in([
                PustakaBerkasBersama::JENIS_TIKET,
                PustakaBerkasBersama::JENIS_HOTEL,
                PustakaBerkasBersama::JENIS_PENYELENGGARAAN,
            ])],
            'nomor' => ['required', 'string', 'max:100'],
            'usulan' => ['nullable', 'string', 'max:100'],
        ], [
            'nomor.required' => 'Isi nomornya lebih dulu.',
        ]);

        // Berkas usulan yang sedang diisi tidak dihitung sebagai milik rekan.
        $kecuali = filled($validated['usulan'] ?? null)
            ? Usulan::where('no_usulan', $validated['usulan'])->value('id')
            : null;

        return response()->json($pustaka->ringkas($validated['jenis'], $validated['nomor'], $kecuali));
    }
}
