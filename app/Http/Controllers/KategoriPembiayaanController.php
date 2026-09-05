<?php

namespace App\Http\Controllers;

use App\Models\KategoriPembiayaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master data kategori pembiayaan — sumber dana yang membiayai perjalanan
 * dinas: RM, BLU, LN, dan seterusnya.
 *
 * Berbeda dari [AkunPembiayaanController] yang mengelola mata anggarannya:
 * satu sumber dana dapat membebani beberapa akun.
 */
class KategoriPembiayaanController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $kategori = KategoriPembiayaan::withCount('nominatif')
            ->when($search, fn ($query) => $query->where(
                fn ($q) => $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")
            ))
            ->orderBy('urutan')
            ->paginate(15)
            ->withQueryString();

        return view('master.kategori-pembiayaan', [
            'kategori' => $kategori,
            'totalKategori' => KategoriPembiayaan::count(),
            'totalAktif' => KategoriPembiayaan::aktif()->count(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aturan());

        $validated['is_aktif'] = $request->boolean('is_aktif', true);
        $validated['urutan'] = $validated['urutan'] ?? (int) KategoriPembiayaan::max('urutan') + 1;

        KategoriPembiayaan::create($validated);

        return redirect()->route('master.kategori-pembiayaan')
            ->with('success', 'Kategori pembiayaan berhasil ditambahkan.');
    }

    public function update(Request $request, KategoriPembiayaan $kategoriPembiayaan): RedirectResponse
    {
        $validated = $request->validate($this->aturan($kategoriPembiayaan));

        $validated['is_aktif'] = $request->boolean('is_aktif');
        $validated['urutan'] = $validated['urutan'] ?? $kategoriPembiayaan->urutan;

        $kategoriPembiayaan->update($validated);

        return redirect()->route('master.kategori-pembiayaan')
            ->with('success', "Kategori \"{$kategoriPembiayaan->kode}\" berhasil diperbarui.");
    }

    public function destroy(KategoriPembiayaan $kategoriPembiayaan): RedirectResponse
    {
        $kode = $kategoriPembiayaan->kode;

        // Menghapus kategori yang sudah membiayai daftar nominatif atau usulan
        // akan membuat berkas lama kehilangan sumber dananya. Nonaktifkan saja.
        if ($kategoriPembiayaan->nominatif()->exists() || $kategoriPembiayaan->usulan()->exists()) {
            return redirect()->route('master.kategori-pembiayaan')
                ->with('error', "Kategori \"{$kode}\" masih dipakai, jadi tidak dapat dihapus. Nonaktifkan saja agar tidak lagi ditawarkan.");
        }

        $kategoriPembiayaan->delete();

        return redirect()->route('master.kategori-pembiayaan')
            ->with('success', "Kategori \"{$kode}\" berhasil dihapus.");
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function aturan(?KategoriPembiayaan $kecuali = null): array
    {
        return [
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique('kategori_pembiayaan', 'kode')->ignore($kecuali?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
