<?php

namespace App\Http\Controllers;

use App\Enums\PesanWhatsapp;
use App\Models\AuditLog;
use App\Services\AuditService;
use App\Services\FormatWhatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Format WhatsApp — submenu Administrasi Sistem: kalimat pesan WhatsApp
 * yang disusun aplikasi, diubah sesuai kebutuhan dengan penanda isian.
 */
class FormatWhatsappController extends Controller
{
    public function __construct(private FormatWhatsapp $format, private AuditService $audit) {}

    public function index(): View
    {
        return view('administrasi.format-whatsapp', [
            'pesan' => PesanWhatsapp::cases(),
            'templat' => collect(PesanWhatsapp::cases())
                ->mapWithKeys(fn (PesanWhatsapp $pesan) => [$pesan->value => $this->format->templat($pesan)])
                ->all(),
            'sudahDiubah' => collect(PesanWhatsapp::cases())
                ->mapWithKeys(fn (PesanWhatsapp $pesan) => [$pesan->value => $this->format->sudahDiubah($pesan)])
                ->all(),
        ]);
    }

    public function simpan(Request $request, string $pesan): RedirectResponse
    {
        $jenis = $this->pesan($pesan);
        $kolom = 'isi_'.$jenis->value;

        $isi = (string) $request->validate([
            $kolom => ['required', 'string', 'max:2000'],
        ], [
            "{$kolom}.required" => 'Format pesan tidak boleh kosong.',
            "{$kolom}.max" => 'Format pesan paling panjang 2.000 karakter.',
        ])[$kolom];

        // Penanda yang salah ketik akan tercetak apa adanya kepada penerima.
        if ($asing = $this->format->penandaAsing($jenis, $isi)) {
            throw ValidationException::withMessages([$kolom => 'Penanda tidak dikenal: {'.implode('}, {', $asing).'}. '
                .'Pakai salah satu penanda yang tersedia di bawah kolom ini.']);
        }

        if ($hilang = $this->format->penandaHilang($jenis, $isi)) {
            throw ValidationException::withMessages([$kolom => 'Penanda {'.implode('}, {', $hilang).'} wajib ada — tanpanya pesan kehilangan isi pokoknya.']);
        }

        $this->format->simpan($jenis, $isi);

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, "Format WhatsApp \"{$jenis->label()}\" diubah.");

        return redirect()
            ->route('administrasi.format-whatsapp')
            ->with('success', "Format WhatsApp \"{$jenis->label()}\" tersimpan dan langsung dipakai pada pesan berikutnya.");
    }

    public function bawaan(string $pesan): RedirectResponse
    {
        $jenis = $this->pesan($pesan);

        $this->format->kembalikanBawaan($jenis);

        $this->audit->catat(AuditLog::AKSI_PENGGUNA, "Format WhatsApp \"{$jenis->label()}\" dikembalikan ke bawaan.");

        return redirect()
            ->route('administrasi.format-whatsapp')
            ->with('success', "Format WhatsApp \"{$jenis->label()}\" kembali seperti bawaan aplikasi.");
    }

    private function pesan(string $kode): PesanWhatsapp
    {
        return PesanWhatsapp::tryFrom($kode) ?? abort(404, 'Jenis pesan WhatsApp itu tidak dikenal.');
    }
}
