<?php

namespace App\Http\Controllers;

use App\Models\AkunPembiayaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master data akun pembiayaan — mata anggaran yang membebani perjalanan
 * dinas. Daftarnya berubah tiap kali DIPA disesuaikan, jadi harus dapat
 * ditambah tanpa menempatkan ulang aplikasi.
 */
class AkunPembiayaanController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $akun = AkunPembiayaan::withCount('nominatif')
            ->when($search, fn ($query) => $query->where(
                fn ($q) => $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%")
            ))
            ->orderBy('urutan')
            ->paginate(15)
            ->withQueryString();

        return view('master.akun-pembiayaan', [
            'akun' => $akun,
            'totalAkun' => AkunPembiayaan::count(),
            'totalAktif' => AkunPembiayaan::aktif()->count(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aturan());

        $validated['is_aktif'] = $request->boolean('is_aktif', true);
        $validated['urutan'] = $validated['urutan'] ?? (int) AkunPembiayaan::max('urutan') + 1;

        AkunPembiayaan::create($validated);

        return redirect()->route('master.akun-pembiayaan')
            ->with('success', 'Akun pembiayaan berhasil ditambahkan.');
    }

    public function update(Request $request, AkunPembiayaan $akunPembiayaan): RedirectResponse
    {
        $validated = $request->validate($this->aturan($akunPembiayaan));

        $validated['is_aktif'] = $request->boolean('is_aktif');
        $validated['urutan'] = $validated['urutan'] ?? $akunPembiayaan->urutan;

        $akunPembiayaan->update($validated);

        return redirect()->route('master.akun-pembiayaan')
            ->with('success', "Akun \"{$akunPembiayaan->kode}\" berhasil diperbarui.");
    }

    public function destroy(AkunPembiayaan $akunPembiayaan): RedirectResponse
    {
        $kode = $akunPembiayaan->kode;

        // Menghapus akun yang sudah membebani daftar nominatif akan membuat
        // daftar lama kehilangan mata anggarannya. Nonaktifkan saja.
        if ($akunPembiayaan->nominatif()->exists()) {
            return redirect()->route('master.akun-pembiayaan')
                ->with('error', "Akun \"{$kode}\" masih dipakai daftar nominatif, jadi tidak dapat dihapus. Nonaktifkan saja agar tidak lagi ditawarkan.");
        }

        $akunPembiayaan->delete();

        return redirect()->route('master.akun-pembiayaan')
            ->with('success', "Akun \"{$kode}\" berhasil dihapus.");
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function aturan(?AkunPembiayaan $kecuali = null): array
    {
        return [
            'kode' => [
                'required',
                'string',
                'max:60',
                Rule::unique('akun_pembiayaan', 'kode')->ignore($kecuali?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
