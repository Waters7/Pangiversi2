<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Panel milik pengguna sendiri: ganti kata sandi dan kelola rekening bank
 * yang dipakai untuk transfer uang perjalanan dinas.
 */
class ProfilController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): View
    {
        $pengguna = $request->user()->load(['unit', 'atasan']);

        return view('profil.index', compact('pengguna'));
    }

    /**
     * Ganti kata sandi sendiri — wajib membuktikan kata sandi lama.
     */
    public function ubahPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password_lama.current_password' => 'Kata sandi lama tidak sesuai.',
        ]);

        $request->user()->update(['password' => $request->password]);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengguna \"{$request->user()->nama}\" mengganti kata sandinya sendiri.",
        );

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }

    /**
     * Unggah atau ganti foto profil.
     *
     * Fotonya dipangkas menjadi bujur sangkar dan diperkecil ke 512 piksel,
     * supaya berkas kamera ponsel tidak tersimpan utuh dan avatar tetap
     * ringan dimuat di setiap halaman.
     */
    public function ubahFoto(Request $request): RedirectResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'foto.image' => 'Berkas foto harus berupa gambar.',
            'foto.max' => 'Ukuran foto paling besar 5 MB.',
        ]);

        $pengguna = $request->user();
        $lama = $pengguna->foto;

        $jalur = 'foto-profil/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($jalur, $this->pangkasPersegi($request->file('foto')->getRealPath()));

        $pengguna->update(['foto' => $jalur]);

        if ($lama) {
            Storage::disk('public')->delete($lama);
        }

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengguna \"{$pengguna->nama}\" memperbarui foto profilnya.",
        );

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    /**
     * Hapus foto profil dan kembali memakai avatar inisial.
     */
    public function hapusFoto(Request $request): RedirectResponse
    {
        $pengguna = $request->user();

        if (blank($pengguna->foto)) {
            return back()->with('error', 'Belum ada foto profil yang dapat dihapus.');
        }

        Storage::disk('public')->delete($pengguna->foto);
        $pengguna->update(['foto' => null]);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            "Pengguna \"{$pengguna->nama}\" menghapus foto profilnya.",
        );

        return back()->with('success', 'Foto profil dihapus, avatar kembali memakai inisial nama.');
    }

    /**
     * Potong bagian tengah gambar menjadi bujur sangkar lalu perkecil.
     */
    private function pangkasPersegi(string $berkas, int $sisi = 512): string
    {
        $asli = imagecreatefromstring(file_get_contents($berkas));

        if ($asli === false) {
            throw ValidationException::withMessages(['foto' => 'Berkas gambar tidak dapat dibaca.']);
        }

        $lebar = imagesx($asli);
        $tinggi = imagesy($asli);
        $potong = min($lebar, $tinggi);

        $hasil = imagecreatetruecolor($sisi, $sisi);
        imagecopyresampled(
            $hasil, $asli,
            0, 0,
            intdiv($lebar - $potong, 2), intdiv($tinggi - $potong, 2),
            $sisi, $sisi, $potong, $potong
        );

        ob_start();
        imagejpeg($hasil, null, 82);
        $isi = (string) ob_get_clean();

        imagedestroy($asli);
        imagedestroy($hasil);

        return $isi;
    }

    /**
     * Perbarui rekening bank tujuan transfer uang perjalanan dinas.
     */
    public function ubahRekening(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_bank' => ['required', 'string', 'max:100'],
            'nomor_rekening' => ['required', 'string', 'max:50', 'regex:/^[0-9\-\s]+$/'],
            'nama_rekening' => ['required', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s]+$/'],
        ], [
            'nomor_rekening.regex' => 'Nomor rekening hanya boleh berisi angka, spasi, atau tanda hubung.',
            'no_hp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda plus, atau tanda hubung.',
        ]);

        $pengguna = $request->user();
        $rekeningLama = $pengguna->nomor_rekening;

        $pengguna->update($validated);

        $this->audit->catat(
            AuditLog::AKSI_PENGGUNA,
            $rekeningLama
                ? "Pengguna \"{$pengguna->nama}\" memperbarui rekening bank tujuan transfer."
                : "Pengguna \"{$pengguna->nama}\" mendaftarkan rekening bank tujuan transfer.",
        );

        return back()->with('success', 'Rekening bank berhasil diperbarui.');
    }
}
