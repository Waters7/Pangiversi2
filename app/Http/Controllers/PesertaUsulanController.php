<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AuditService;
use App\Services\NotifikasiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PesertaUsulanController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private NotifikasiService $notifikasi,
    ) {}

    public function store(Request $request, Usulan $usulan): RedirectResponse
    {
        $this->authorizeKelola($request, $usulan);

        $validated = $request->validate([
            'id_user' => [
                'nullable', 'exists:users,id',
                Rule::unique('peserta_usulan', 'id_user')
                    ->where(fn ($query) => $query->where('id_usulan', $usulan->id)),
            ],
            'nama' => ['required_without:id_user', 'nullable', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'peran' => ['required', Rule::in(array_keys(PesertaUsulan::peranOptions()))],
        ], [
            'id_user.unique' => 'Pegawai tersebut sudah terdaftar sebagai peserta.',
            'nama.required_without' => 'Isi nama peserta atau pilih pegawai dari daftar.',
        ]);

        // Peserta yang dipilih dari daftar pegawai mengambil identitas dari profilnya.
        if ($validated['id_user'] ?? null) {
            $pegawai = User::findOrFail($validated['id_user']);

            $validated['nama'] = $pegawai->nama;
            $validated['nip'] = $pegawai->nip;
            $validated['jabatan'] = $pegawai->jabatan;
        }

        // Hanya boleh ada satu ketua tim per usulan.
        if ($validated['peran'] === 'ketua') {
            $usulan->peserta()->where('peran', 'ketua')->update(['peran' => 'anggota']);
        }

        $usulan->peserta()->create($validated);

        $this->audit->catat(
            AuditLog::AKSI_PESERTA,
            "Peserta \"{$validated['nama']}\" ditambahkan pada usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        return back()->with('success', "Peserta \"{$validated['nama']}\" berhasil ditambahkan.");
    }

    public function destroy(Request $request, Usulan $usulan, PesertaUsulan $peserta): RedirectResponse
    {
        $this->authorizeKelola($request, $usulan);

        abort_if($peserta->id_usulan !== $usulan->id, 404);

        $nama = $peserta->nama;
        $peserta->delete();

        $this->audit->catat(
            AuditLog::AKSI_PESERTA,
            "Peserta \"{$nama}\" dihapus dari usulan {$usulan->no_usulan}.",
            ['usulan' => $usulan],
        );

        return back()->with('success', "Peserta \"{$nama}\" berhasil dihapus.");
    }

    /**
     * Peserta hanya boleh diubah oleh pengusul (selama usulan masih bisa disunting)
     * atau oleh administrator.
     */
    private function authorizeKelola(Request $request, Usulan $usulan): void
    {
        // Kunci status berlaku untuk semua peran, termasuk administrator:
        // susunan peserta pada usulan yang sudah divalidasi menjadi dasar
        // penerbitan SPPD dan pembayaran, sehingga tidak boleh bergeser.
        abort_unless(
            $usulan->bolehMengubahPeserta(),
            403,
            $usulan->alasanPesertaTerkunci() ?? 'Daftar peserta usulan ini terkunci.'
        );

        if ($request->user()->isAdmin()) {
            return;
        }

        abort_if($usulan->id_user !== $request->user()->id, 403, 'Anda bukan pengusul dari usulan ini.');
    }
}
