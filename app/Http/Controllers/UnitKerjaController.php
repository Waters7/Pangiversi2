<?php

namespace App\Http\Controllers;

use App\Models\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitKerjaController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $unitKerja = UnitKerja::withCount('pegawai')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode', 'like', "%{$search}%");
                });
            })
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        $totalUnit = UnitKerja::count();
        $totalAktif = UnitKerja::aktif()->count();

        return view('master.unit-kerja', compact('unitKerja', 'totalUnit', 'totalAktif', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:unit_kerja,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $validated['kode'] = strtoupper($validated['kode']);
        $validated['is_aktif'] = $request->boolean('is_aktif', true);

        UnitKerja::create($validated);

        return redirect()->route('master.unit-kerja')
            ->with('success', 'Unit kerja berhasil ditambahkan.');
    }

    public function update(Request $request, UnitKerja $unitKerja): RedirectResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', Rule::unique('unit_kerja', 'kode')->ignore($unitKerja->id)],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $validated['kode'] = strtoupper($validated['kode']);
        $validated['is_aktif'] = $request->boolean('is_aktif');

        $unitKerja->update($validated);

        return redirect()->route('master.unit-kerja')
            ->with('success', "Unit kerja \"{$unitKerja->nama}\" berhasil diperbarui.");
    }

    public function destroy(UnitKerja $unitKerja): RedirectResponse
    {
        $nama = $unitKerja->nama;

        if ($unitKerja->pegawai()->exists()) {
            return redirect()->route('master.unit-kerja')
                ->with('error', "Unit kerja \"{$nama}\" tidak dapat dihapus karena masih memiliki pegawai.");
        }

        $unitKerja->delete();

        return redirect()->route('master.unit-kerja')
            ->with('success', "Unit kerja \"{$nama}\" berhasil dihapus.");
    }
}
