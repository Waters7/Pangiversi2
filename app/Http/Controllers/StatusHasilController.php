<?php

namespace App\Http\Controllers;

use App\Models\StatusHasil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master data status hasil laporan perjalanan dinas.
 *
 * Tiga pilihan bakunya — selesai dikerjakan, perlu tindak lanjut, tidak
 * selesai — dapat ditambah satuan kerja sendiri tanpa menunggu aplikasi
 * ditempatkan ulang.
 */
class StatusHasilController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $status = StatusHasil::withCount('laporan')
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('master.status-hasil', [
            'status' => $status,
            'totalStatus' => StatusHasil::count(),
            'totalAktif' => StatusHasil::aktif()->count(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aturan());

        $validated['is_aktif'] = $request->boolean('is_aktif', true);
        $validated['urutan'] = $validated['urutan'] ?? ((int) StatusHasil::max('urutan') + 1);

        StatusHasil::create($validated);

        return redirect()->route('master.status-hasil')
            ->with('success', 'Status hasil berhasil ditambahkan.');
    }

    public function update(Request $request, StatusHasil $statusHasil): RedirectResponse
    {
        $validated = $request->validate($this->aturan($statusHasil));

        $validated['is_aktif'] = $request->boolean('is_aktif');
        $validated['urutan'] = $validated['urutan'] ?? $statusHasil->urutan;

        $statusHasil->update($validated);

        return redirect()->route('master.status-hasil')
            ->with('success', "Status \"{$statusHasil->nama}\" berhasil diperbarui.");
    }

    public function destroy(StatusHasil $statusHasil): RedirectResponse
    {
        $nama = $statusHasil->nama;

        // Menghapus status yang terpakai membuat laporan lama kehilangan
        // keterangan hasilnya. Nonaktifkan saja: laporan lama tetap terbaca,
        // statusnya berhenti ditawarkan pada formulir.
        if ($statusHasil->laporan()->exists()) {
            return redirect()->route('master.status-hasil')
                ->with('error', "Status \"{$nama}\" masih dipakai laporan, jadi tidak dapat dihapus. Nonaktifkan saja agar tidak lagi ditawarkan.");
        }

        $statusHasil->delete();

        return redirect()->route('master.status-hasil')
            ->with('success', "Status \"{$nama}\" berhasil dihapus.");
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function aturan(?StatusHasil $kecuali = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:100',
                Rule::unique('status_hasil', 'nama')->ignore($kecuali?->id),
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
