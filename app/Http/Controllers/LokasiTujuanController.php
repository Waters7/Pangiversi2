<?php

namespace App\Http\Controllers;

use App\Models\LokasiTujuan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LokasiTujuanController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $jenis = $request->input('jenis');

        $lokasi = LokasiTujuan::withCount('usulan')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('provinsi', 'like', "%{$search}%");
                });
            })
            ->when($jenis, fn ($query) => $query->where('jenis', $jenis))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $totalLokasi = LokasiTujuan::count();
        $jenisOptions = LokasiTujuan::jenisOptions();

        return view('master.lokasi-tujuan', compact('lokasi', 'totalLokasi', 'jenisOptions', 'search', 'jenis'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $validated['is_aktif'] = $request->boolean('is_aktif', true);

        LokasiTujuan::create($validated);

        return redirect()->route('master.lokasi')
            ->with('success', 'Lokasi tujuan berhasil ditambahkan.');
    }

    public function update(Request $request, LokasiTujuan $lokasi): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $validated['is_aktif'] = $request->boolean('is_aktif');

        $lokasi->update($validated);

        return redirect()->route('master.lokasi')
            ->with('success', "Lokasi \"{$lokasi->nama}\" berhasil diperbarui.");
    }

    public function destroy(LokasiTujuan $lokasi): RedirectResponse
    {
        $nama = $lokasi->nama;

        if ($lokasi->usulan()->exists()) {
            return redirect()->route('master.lokasi')
                ->with('error', "Lokasi \"{$nama}\" tidak dapat dihapus karena masih digunakan oleh usulan.");
        }

        $lokasi->delete();

        return redirect()->route('master.lokasi')
            ->with('success', "Lokasi \"{$nama}\" berhasil dihapus.");
    }

    /**
     * Validation rules shared by store and update.
     *
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(array_keys(LokasiTujuan::jenisOptions()))],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
