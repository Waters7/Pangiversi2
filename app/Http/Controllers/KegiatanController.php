<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KegiatanController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $kegiatan = Kegiatan::withCount('kegiatan as usulan_count')
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $totalKegiatan = Kegiatan::count();

        return view('kegiatan.index', compact('kegiatan', 'totalKegiatan', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', 'unique:kegiatan,nama'],
        ]);

        Kegiatan::create($validated);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Jenis kegiatan berhasil ditambahkan.');
    }

    public function update(Request $request, Kegiatan $kegiatan): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255', Rule::unique('kegiatan', 'nama')->ignore($kegiatan->id)],
        ]);

        $kegiatan->update($validated);

        return redirect()->route('kegiatan.index')
            ->with('success', "Kegiatan \"{$kegiatan->nama}\" berhasil diperbarui.");
    }

    public function destroy(Kegiatan $kegiatan): RedirectResponse
    {
        $nama = $kegiatan->nama;

        if ($kegiatan->kegiatan()->exists()) {
            return redirect()->route('kegiatan.index')
                ->with('error', "Kegiatan \"{$nama}\" tidak dapat dihapus karena masih digunakan oleh usulan.");
        }

        $kegiatan->delete();

        return redirect()->route('kegiatan.index')
            ->with('success', "Kegiatan \"{$nama}\" berhasil dihapus.");
    }
}
