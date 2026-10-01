<?php

namespace App\Http\Controllers;

use App\Enums\BerkasLpj;
use App\Enums\JenisPerjadin;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\PengaturanBerkasLpj;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Berkas Pertanggungjawaban — submenu Administrasi Sistem: memilih berkas
 * apa saja yang ditagih sesudah perjalanan, untuk tiap jalur pengajuan.
 */
class BerkasLpjController extends Controller
{
    public function __construct(private PengaturanBerkasLpj $pengaturan, private AuditService $audit) {}

    public function index(): View
    {
        return view('administrasi.berkas-lpj', [
            'jalur' => JenisPerjadin::cases(),
            'berkas' => BerkasLpj::cases(),
            'terpilih' => collect(JenisPerjadin::cases())
                ->mapWithKeys(fn (JenisPerjadin $jenis) => [$jenis->value => $this->pengaturan->untuk($jenis)])
                ->all(),
            'sudahDiatur' => collect(JenisPerjadin::cases())
                ->mapWithKeys(fn (JenisPerjadin $jenis) => [$jenis->value => $this->pengaturan->sudahDiatur($jenis)])
                ->all(),
        ]);
    }

    public function simpan(Request $request, string $jalur): RedirectResponse
    {
        $jenis = $this->jalur($jalur);

        $data = $request->validate([
            'berkas' => ['nullable', 'array'],
            'berkas.*' => ['nullable'],
        ]);

        $sebelum = $this->pengaturan->untuk($jenis);
        $this->pengaturan->simpan($jenis, $data['berkas'] ?? []);
        $sesudah = $this->pengaturan->untuk($jenis);

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, $this->uraian($jenis, $sebelum, $sesudah));

        return redirect()
            ->route('administrasi.berkas-lpj')
            ->with('success', "Berkas pertanggungjawaban jalur \"{$jenis->label()}\" tersimpan dan langsung berlaku pada checklist serta penagihan.");
    }

    public function bawaan(string $jalur): RedirectResponse
    {
        $jenis = $this->jalur($jalur);

        $this->pengaturan->kembalikanBawaan($jenis);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Berkas pertanggungjawaban jalur \"{$jenis->label()}\" dikembalikan ke bawaan.",
        );

        return redirect()
            ->route('administrasi.berkas-lpj')
            ->with('success', "Berkas pertanggungjawaban jalur \"{$jenis->label()}\" kembali seperti bawaan aplikasi.");
    }

    private function jalur(string $kode): JenisPerjadin
    {
        return JenisPerjadin::dari($kode) ?? abort(404, 'Jalur pengajuan itu tidak dikenal.');
    }

    /**
     * @param  list<BerkasLpj>  $sebelum
     * @param  list<BerkasLpj>  $sesudah
     */
    private function uraian(JenisPerjadin $jenis, array $sebelum, array $sesudah): string
    {
        $nama = fn (array $daftar) => array_map(fn (BerkasLpj $b) => $b->label(), $daftar);

        $ditambah = array_diff($nama($sesudah), $nama($sebelum));
        $dicabut = array_diff($nama($sebelum), $nama($sesudah));

        $bagian = [];
        if ($ditambah !== []) {
            $bagian[] = 'ditambah: '.implode(', ', $ditambah);
        }
        if ($dicabut !== []) {
            $bagian[] = 'tidak lagi ditagih: '.implode(', ', $dicabut);
        }

        return "Berkas pertanggungjawaban jalur \"{$jenis->label()}\" disimpan"
            .($bagian !== [] ? ' — '.implode('; ', $bagian) : ' tanpa perubahan').'.';
    }
}
