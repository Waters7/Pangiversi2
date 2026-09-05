<?php

namespace App\Http\Controllers;

use App\Models\KomponenBiaya;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KomponenBiayaController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $jenis = $request->input('jenis');

        $komponen = KomponenBiaya::query()
            ->when($search, fn ($query) => $query->where('nama', 'like', "%{$search}%"))
            ->when($jenis, fn ($query) => $query->where('jenis', $jenis))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $totalKomponen = KomponenBiaya::count();
        $jenisOptions = KomponenBiaya::jenisOptions();

        return view('master.komponen-biaya', compact('komponen', 'totalKomponen', 'jenisOptions', 'search', 'jenis'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $validated['is_aktif'] = $request->boolean('is_aktif', true);

        KomponenBiaya::create($validated);

        return redirect()->route('master.komponen-biaya')
            ->with('success', 'Komponen biaya berhasil ditambahkan.');
    }

    public function update(Request $request, KomponenBiaya $komponenBiaya): RedirectResponse
    {
        $validated = $request->validate($this->rules($komponenBiaya));

        $validated['is_aktif'] = $request->boolean('is_aktif');

        $komponenBiaya->update($validated);

        return redirect()->route('master.komponen-biaya')
            ->with('success', "Komponen \"{$komponenBiaya->nama}\" berhasil diperbarui.");
    }

    public function destroy(KomponenBiaya $komponenBiaya): RedirectResponse
    {
        $nama = $komponenBiaya->nama;

        $komponenBiaya->delete();

        return redirect()->route('master.komponen-biaya')
            ->with('success', "Komponen \"{$nama}\" berhasil dihapus.");
    }

    /**
     * Validation rules shared by store and update.
     *
     * @return array<string, list<mixed>>
     */
    private function rules(?KomponenBiaya $komponenBiaya = null): array
    {
        return [
            'nama' => [
                'required', 'string', 'max:255',
                Rule::unique('komponen_biaya', 'nama')->ignore($komponenBiaya?->id),
            ],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_satuan' => ['required', 'numeric', 'min:0'],
            'jenis' => ['required', Rule::in(array_keys(KomponenBiaya::jenisOptions()))],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }
}
