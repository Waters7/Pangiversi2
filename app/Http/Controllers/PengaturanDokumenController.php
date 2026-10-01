<?php

namespace App\Http\Controllers;

use App\Enums\DokumenCetak;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\KertasCetak;
use App\Services\PengaturanDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dokumen Output — submenu Administrasi Sistem: mengatur tampilan dokumen
 * cetak (ukuran kertas dan huruf), elemen apa saja yang ikut tercetak, dan
 * teks bakunya.
 */
class PengaturanDokumenController extends Controller
{
    public function __construct(private PengaturanDokumen $pengaturan, private AuditService $audit) {}

    public function index(Request $request): View
    {
        $terpilih = DokumenCetak::dari($request->query('dokumen')) ?? DokumenCetak::Perjadin;

        return view('administrasi.dokumen', [
            'daftar' => DokumenCetak::cases(),
            'terpilih' => $terpilih,
            'atur' => $this->pengaturan->untuk($terpilih),
            'sudahDiatur' => $this->pengaturan->sudahDiatur($terpilih),
            'pilihanKertas' => KertasCetak::PILIHAN,
        ]);
    }

    public function simpan(Request $request, string $dokumen): RedirectResponse
    {
        $terpilih = $this->dokumen($dokumen);

        $data = $request->validate([
            'kertas' => ['required', 'string', 'in:'.implode(',', array_keys(KertasCetak::PILIHAN))],
            'huruf' => ['required', 'numeric', 'between:6,16'],
            'lebar_kop' => ['nullable', 'integer', 'between:40,100'],
            'elemen' => ['nullable', 'array'],
            'teks' => ['nullable', 'array'],
            'teks.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $sebelum = $this->pengaturan->untuk($terpilih);
        $dimatikan = $this->selisihElemen($terpilih, $sebelum->isi()['elemen'], $data['elemen'] ?? []);

        $this->pengaturan->simpan($terpilih, $data);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Tampilan dokumen \"{$terpilih->label()}\" disimpan".($dimatikan !== [] ? ' — elemen disembunyikan: '.implode(', ', $dimatikan).'.' : '.'),
        );

        return redirect()
            ->route('administrasi.dokumen', ['dokumen' => $terpilih->value])
            ->with('success', "Tampilan dokumen \"{$terpilih->label()}\" tersimpan dan langsung berlaku pada cetakan berikutnya.");
    }

    public function bawaan(string $dokumen): RedirectResponse
    {
        $terpilih = $this->dokumen($dokumen);

        $this->pengaturan->kembalikanBawaan($terpilih);

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, "Tampilan dokumen \"{$terpilih->label()}\" dikembalikan ke bawaan.");

        return redirect()
            ->route('administrasi.dokumen', ['dokumen' => $terpilih->value])
            ->with('success', "Tampilan dokumen \"{$terpilih->label()}\" kembali seperti bawaan aplikasi.");
    }

    private function dokumen(string $kode): DokumenCetak
    {
        return DokumenCetak::dari($kode) ?? abort(404, 'Dokumen cetak itu tidak dikenal.');
    }

    /**
     * Nama elemen yang baru saja dimatikan — dicatat pada jejak audit.
     *
     * @param  array<string, bool>  $sebelum
     * @param  array<string, mixed>  $sesudah
     * @return list<string>
     */
    private function selisihElemen(DokumenCetak $dokumen, array $sebelum, array $sesudah): array
    {
        $dimatikan = [];

        foreach ($dokumen->elemen() as $kode => $tentang) {
            if (($sebelum[$kode] ?? true) && ! ($sesudah[$kode] ?? false)) {
                $dimatikan[] = $tentang['label'];
            }
        }

        return $dimatikan;
    }
}
