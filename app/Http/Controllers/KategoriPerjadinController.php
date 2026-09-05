<?php

namespace App\Http\Controllers;

use App\Models\KategoriPerjadin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master data kategori perjalanan dinas.
 *
 * Kategori menentukan kelas biaya — fullboard, fullday, halfday, dan
 * seterusnya. Sebelumnya daftarnya hanya dapat diubah lewat seeder, sehingga
 * kategori baru menuntut penempatan ulang aplikasi.
 */
class KategoriPerjadinController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $grup = $request->input('grup');

        $kategori = KategoriPerjadin::withCount('usulan')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode', 'like', "%{$search}%");
                });
            })
            ->when($grup, fn ($query) => $query->where('grup', $grup))
            ->orderBy('grup')
            ->orderBy('urutan')
            ->paginate(15)
            ->withQueryString();

        return view('master.kategori-perjadin', [
            'kategori' => $kategori,
            'grupOptions' => KategoriPerjadin::URUTAN_GRUP,
            'totalKategori' => KategoriPerjadin::count(),
            'totalAktif' => KategoriPerjadin::aktif()->count(),
            'search' => $search,
            'grup' => $grup,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aturan());

        $validated['is_aktif'] = $request->boolean('is_aktif', true);
        $validated['dalam_kota'] = $request->boolean('dalam_kota');
        $validated['urutan'] = $validated['urutan'] ?? $this->urutanBerikutnya($validated['grup']);

        KategoriPerjadin::create($validated);

        return redirect()->route('master.kategori-perjadin')
            ->with('success', 'Kategori perjalanan dinas berhasil ditambahkan.');
    }

    public function update(Request $request, KategoriPerjadin $kategoriPerjadin): RedirectResponse
    {
        $validated = $request->validate($this->aturan($kategoriPerjadin));

        $validated['is_aktif'] = $request->boolean('is_aktif');
        $validated['dalam_kota'] = $request->boolean('dalam_kota');
        $validated['urutan'] = $validated['urutan'] ?? $kategoriPerjadin->urutan;

        $kategoriPerjadin->update($validated);

        return redirect()->route('master.kategori-perjadin')
            ->with('success', "Kategori \"{$kategoriPerjadin->nama}\" berhasil diperbarui.");
    }

    public function destroy(KategoriPerjadin $kategoriPerjadin): RedirectResponse
    {
        $nama = $kategoriPerjadin->nama;

        // Menghapus kategori yang terpakai akan membuat usulan lama kehilangan
        // kelas biayanya. Nonaktifkan saja: usulan lama tetap terbaca, kategori
        // itu berhenti ditawarkan pada formulir.
        if ($kategoriPerjadin->usulan()->exists()) {
            return redirect()->route('master.kategori-perjadin')
                ->with('error', "Kategori \"{$nama}\" masih dipakai usulan, jadi tidak dapat dihapus. Nonaktifkan saja agar tidak lagi ditawarkan.");
        }

        $kategoriPerjadin->delete();

        return redirect()->route('master.kategori-perjadin')
            ->with('success', "Kategori \"{$nama}\" berhasil dihapus.");
    }

    /**
     * Urutan tampil berikutnya dalam satu grup.
     */
    private function urutanBerikutnya(string $grup): int
    {
        return (int) KategoriPerjadin::where('grup', $grup)->max('urutan') + 1;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function aturan(?KategoriPerjadin $kecuali = null): array
    {
        return [
            'grup' => ['required', Rule::in(KategoriPerjadin::URUTAN_GRUP)],
            'nama' => ['required', 'string', 'max:255'],
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique('kategori_perjadin', 'kode')->ignore($kecuali?->id),
            ],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
            'dalam_kota' => ['nullable', 'boolean'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
