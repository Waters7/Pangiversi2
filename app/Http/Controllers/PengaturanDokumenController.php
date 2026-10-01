<?php

namespace App\Http\Controllers;

use App\Enums\DokumenCetak;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\ContohDokumen;
use App\Services\KertasCetak;
use App\Services\PengaturanDokumen;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Dokumen Output — submenu Administrasi Sistem: mengatur tampilan dokumen
 * cetak (ukuran kertas dan huruf), elemen apa saja yang ikut tercetak, dan
 * teks bakunya.
 */
class PengaturanDokumenController extends Controller
{
    public function __construct(
        private PengaturanDokumen $pengaturan,
        private AuditService $audit,
        private ContohDokumen $contoh,
    ) {}

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

    /**
     * Pratinjau dokumen dengan data contoh.
     *
     * Dibuka lewat POST dari formulir — supaya setelan yang belum disimpan
     * ikut terlihat — atau lewat GET memakai setelan yang sudah tersimpan.
     * Keluarannya HTML agar cepat; tambahkan ?pdf=1 untuk berkas PDF-nya.
     */
    public function pratinjau(Request $request, string $dokumen): Response
    {
        $terpilih = $this->dokumen($dokumen);

        // Setelan dari formulir hanya berlaku untuk tampilan ini; yang
        // tersimpan di basis data tidak disentuh sedikit pun.
        if ($request->isMethod('post')) {
            $this->pengaturan->sementara($terpilih, $request->all());
        }

        $isi = view($terpilih->view(), $this->contoh->bekal($terpilih))->render();

        if (! $request->boolean('pdf')) {
            // Berkas cetak menunjuk gambar lewat jalur berkas supaya dompdf
            // dapat membacanya; peramban perlu alamat web.
            return response($this->gambarKeAlamatWeb($isi));
        }

        if ($request->boolean('pdf')) {
            $pdf = Pdf::loadHTML($isi)->setPaper(
                $this->pengaturan->untuk($terpilih)->kertas(),
                $terpilih->orientasi(),
            );

            return $pdf->stream($this->contoh->namaBerkas($terpilih));
        }

        return response($isi);
    }

    /**
     * Ganti jalur berkas gambar pada pratinjau dengan alamat web.
     */
    private function gambarKeAlamatWeb(string $isi): string
    {
        $akar = str_replace(DIRECTORY_SEPARATOR, '/', public_path());

        return preg_replace_callback('/src="([^"]+)"/', function (array $cocok) use ($akar) {
            $jalur = str_replace(DIRECTORY_SEPARATOR, '/', $cocok[1]);

            // Alamat relatif, supaya benar baik dibuka lewat localhost
            // maupun lewat alamat IP di jaringan.
            return str_starts_with($jalur, $akar)
                ? 'src="'.substr($jalur, strlen($akar)).'"'
                : $cocok[0];
        }, $isi);
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
