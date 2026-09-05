<?php

namespace App\Http\Controllers;

use App\Models\TahunAnggaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TahunAnggaranController extends Controller
{
    public function index(): View
    {
        $tahunAnggaran = TahunAnggaran::withCount('usulan')
            ->orderByDesc('tahun')
            ->paginate(10);

        $aktif = TahunAnggaran::aktif();

        return view('master.tahun-anggaran', compact('tahunAnggaran', 'aktif'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:tahun_anggaran,tahun'],
            'pagu' => ['required', 'numeric', 'min:0'],
        ]);

        $tahunAnggaran = TahunAnggaran::create($validated + ['is_aktif' => false]);

        if ($request->boolean('is_aktif')) {
            $tahunAnggaran->aktifkan();
        }

        return redirect()->route('master.tahun-anggaran')
            ->with('success', "Tahun anggaran {$tahunAnggaran->tahun} berhasil ditambahkan.");
    }

    public function update(Request $request, TahunAnggaran $tahunAnggaran): RedirectResponse
    {
        $validated = $request->validate([
            'tahun' => [
                'required', 'integer', 'min:2000', 'max:2100',
                Rule::unique('tahun_anggaran', 'tahun')->ignore($tahunAnggaran->id),
            ],
            'pagu' => ['required', 'numeric', 'min:0'],
        ]);

        $tahunAnggaran->update($validated);

        if ($request->boolean('is_aktif')) {
            $tahunAnggaran->aktifkan();
        }

        return redirect()->route('master.tahun-anggaran')
            ->with('success', "Tahun anggaran {$tahunAnggaran->tahun} berhasil diperbarui.");
    }

    public function aktifkan(TahunAnggaran $tahunAnggaran): RedirectResponse
    {
        $tahunAnggaran->aktifkan();

        return redirect()->route('master.tahun-anggaran')
            ->with('success', "Tahun anggaran {$tahunAnggaran->tahun} kini menjadi tahun aktif.");
    }

    public function destroy(TahunAnggaran $tahunAnggaran): RedirectResponse
    {
        $tahun = $tahunAnggaran->tahun;

        if ($tahunAnggaran->usulan()->exists()) {
            return redirect()->route('master.tahun-anggaran')
                ->with('error', "Tahun anggaran {$tahun} tidak dapat dihapus karena masih memiliki usulan.");
        }

        $tahunAnggaran->delete();

        return redirect()->route('master.tahun-anggaran')
            ->with('success', "Tahun anggaran {$tahun} berhasil dihapus.");
    }
}
