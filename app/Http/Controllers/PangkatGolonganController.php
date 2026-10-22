<?php

namespace App\Http\Controllers;

use App\Models\PangkatGolongan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master data pilihan "Pangkat dan Golongan".
 *
 * Daftar PNS dan PPPK sudah terisi; satuan kerja menambah sendiri pilihan
 * lain — misalnya tenaga outsourcing atau honorer — yang lalu ditawarkan
 * pada formulir pengguna dan Surat Perjalanan Dinas.
 */
class PangkatGolonganController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');

        $golongan = PangkatGolongan::withCount('pengguna')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('kode', 'like', "%{$search}%")
                ->orWhere('tulisan', 'like', "%{$search}%")
                ->orWhere('kelompok', 'like', "%{$search}%")))
            ->orderBy('urutan')
            ->orderBy('tulisan')
            ->paginate(50)
            ->withQueryString();

        return view('master.pangkat-golongan', [
            'golongan' => $golongan,
            'totalGolongan' => PangkatGolongan::count(),
            'daftarKelompok' => PangkatGolongan::orderBy('urutan')->pluck('kelompok')->unique()->values(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->aturan(), $this->pesan());

        $validated['urutan'] = $validated['urutan'] ?? ((int) PangkatGolongan::max('urutan') + 1);

        PangkatGolongan::create($validated);

        return redirect()->route('master.pangkat-golongan')
            ->with('success', "Pangkat dan golongan \"{$validated['tulisan']}\" berhasil ditambahkan.");
    }

    public function update(Request $request, PangkatGolongan $pangkatGolongan): RedirectResponse
    {
        $validated = $request->validate($this->aturan($pangkatGolongan), $this->pesan());

        $validated['urutan'] = $validated['urutan'] ?? $pangkatGolongan->urutan;
        $kodeLama = $pangkatGolongan->kode;

        // Kode yang berganti ikut diganti pada pengguna yang memakainya,
        // supaya golongan mereka tidak tiba-tiba kosong.
        DB::transaction(function () use ($pangkatGolongan, $validated, $kodeLama): void {
            $pangkatGolongan->update($validated);

            if ($kodeLama !== $pangkatGolongan->kode) {
                User::where('golongan', $kodeLama)->update(['golongan' => $pangkatGolongan->kode]);
            }
        });

        return redirect()->route('master.pangkat-golongan')
            ->with('success', "Pangkat dan golongan \"{$pangkatGolongan->tulisan}\" berhasil diperbarui.");
    }

    public function destroy(PangkatGolongan $pangkatGolongan): RedirectResponse
    {
        $tulisan = $pangkatGolongan->tulisan;
        $dipakai = $pangkatGolongan->pengguna()->count();

        // Menghapus pilihan yang masih dipakai membuat golongan pegawai itu
        // tidak terbaca lagi pada SPD.
        if ($dipakai > 0) {
            return redirect()->route('master.pangkat-golongan')
                ->with('error', "\"{$tulisan}\" masih dipakai {$dipakai} pengguna, jadi tidak dapat dihapus. Ganti dulu golongan pengguna tersebut.");
        }

        $pangkatGolongan->delete();

        return redirect()->route('master.pangkat-golongan')
            ->with('success', "Pangkat dan golongan \"{$tulisan}\" berhasil dihapus.");
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function aturan(?PangkatGolongan $kecuali = null): array
    {
        return [
            // Panjangnya mengikuti kolom golongan pengguna.
            'kode' => ['required', 'string', 'max:8', 'regex:/^\S+$/', Rule::unique('pangkat_golongan', 'kode')->ignore($kecuali?->id)],
            'tulisan' => ['required', 'string', 'max:100', Rule::unique('pangkat_golongan', 'tulisan')->ignore($kecuali?->id)],
            'kelompok' => ['required', 'string', 'max:60'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pesan(): array
    {
        return [
            'kode.max' => 'Kode paling panjang 8 karakter.',
            'kode.regex' => 'Kode tidak boleh memuat spasi.',
            'kode.unique' => 'Kode itu sudah dipakai pilihan lain.',
            'tulisan.unique' => 'Tulisan itu sudah ada.',
        ];
    }
}
