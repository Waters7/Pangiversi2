<?php

namespace App\Http\Controllers;

use App\Models\AkunPembiayaan;
use App\Models\KategoriPembiayaan;
use App\Models\PaguAkun;
use App\Models\TahunAnggaran;
use App\Services\RingkasanEksekutif;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TahunAnggaranController extends Controller
{
    public function index(): View
    {
        $tahunAnggaran = TahunAnggaran::withCount('usulan', 'paguAkun')
            ->orderByDesc('tahun')
            ->paginate(10);

        $aktif = TahunAnggaran::aktif();

        return view('master.tahun-anggaran', [
            'tahunAnggaran' => $tahunAnggaran,
            'aktif' => $aktif,
            'anggaranAktif' => $aktif ? app(RingkasanEksekutif::class)->anggaranPerAkun($aktif->tahun) : null,
        ]);
    }

    /**
     * Pagu per kode akun satu tahun anggaran — angka maksimal tiap mata
     * anggaran beserta sumber dananya, berdampingan dengan realisasinya.
     */
    public function paguAkun(TahunAnggaran $tahunAnggaran, RingkasanEksekutif $ringkasan): View
    {
        return view('master.pagu-akun', [
            'tahunAnggaran' => $tahunAnggaran,
            'paguAkun' => $tahunAnggaran->paguAkun()
                ->with('akun', 'sumberDana')
                ->get()
                ->sortBy(fn (PaguAkun $baris) => [$baris->akun?->urutan, $baris->akun?->kode])
                ->values(),
            'anggaran' => $ringkasan->anggaranPerAkun($tahunAnggaran->tahun),
            'pilihanAkun' => AkunPembiayaan::pilihan(),
            'pilihanSumber' => KategoriPembiayaan::pilihan(),
        ]);
    }

    /**
     * Tetapkan pagu satu kode akun. Akun yang sudah berpagu diperbarui,
     * bukan digandakan; pagu tahun anggaran ikut menjadi jumlahnya.
     */
    public function simpanPaguAkun(Request $request, TahunAnggaran $tahunAnggaran): RedirectResponse
    {
        $validated = $request->validate([
            'id_akun_pembiayaan' => ['required', 'exists:akun_pembiayaan,id'],
            'id_kategori_pembiayaan' => ['nullable', 'exists:kategori_pembiayaan,id'],
            'pagu' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ], [
            'id_akun_pembiayaan.required' => 'Pilih kode akunnya.',
            'pagu.required' => 'Isi pagu akun ini.',
        ]);

        $baris = $tahunAnggaran->paguAkun()->updateOrCreate(
            ['id_akun_pembiayaan' => $validated['id_akun_pembiayaan']],
            ['id_kategori_pembiayaan' => $validated['id_kategori_pembiayaan'] ?? null, 'pagu' => $validated['pagu']],
        );

        $tahunAnggaran->selaraskanPagu();

        return redirect()->route('master.tahun-anggaran.pagu-akun', $tahunAnggaran)
            ->with('success', "Pagu akun {$baris->akun->kode} tahun {$tahunAnggaran->tahun} tersimpan. Pagu tahun anggaran kini Rp "
                .number_format($tahunAnggaran->fresh()->pagu, 0, ',', '.').'.');
    }

    public function hapusPaguAkun(TahunAnggaran $tahunAnggaran, PaguAkun $paguAkun): RedirectResponse
    {
        abort_unless($paguAkun->id_tahun_anggaran === $tahunAnggaran->id, 404);

        $kode = $paguAkun->akun?->kode;
        $paguAkun->delete();

        $tahunAnggaran->selaraskanPagu();

        return redirect()->route('master.tahun-anggaran.pagu-akun', $tahunAnggaran)
            ->with('success', "Pagu akun {$kode} tahun {$tahunAnggaran->tahun} dihapus.");
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
            // Pagu yang sudah dirinci per akun mengikuti jumlah pagu akunnya.
            'pagu' => [Rule::requiredIf(! $tahunAnggaran->dirinciPerAkun()), 'nullable', 'numeric', 'min:0'],
        ]);

        if ($tahunAnggaran->dirinciPerAkun()) {
            unset($validated['pagu']);
        }

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
