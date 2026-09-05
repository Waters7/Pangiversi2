<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Support\Collection;

/**
 * Mengirim notifikasi dalam aplikasi untuk aksi penting seperti pengajuan,
 * persetujuan, penolakan, permintaan revisi, dan kelengkapan LPJ (FR-15).
 */
class NotifikasiService
{
    /**
     * Kirim notifikasi ke satu pengguna.
     *
     * @param  array{usulan?: ?Usulan, tipe?: string, url?: ?string}  $opsi
     */
    public function kirim(User $penerima, string $judul, string $pesan, array $opsi = []): Notifikasi
    {
        $usulan = $opsi['usulan'] ?? null;

        return Notifikasi::create([
            'id_user' => $penerima->id,
            'id_usulan' => $usulan?->id,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => $opsi['tipe'] ?? Notifikasi::TIPE_INFO,
            'url' => $opsi['url'] ?? ($usulan ? route('usulan.show', $usulan->no_usulan) : null),
        ]);
    }

    /**
     * Kirim notifikasi yang sama ke sekumpulan pengguna, tanpa duplikat.
     *
     * @param  iterable<User>  $penerima
     * @param  array{usulan?: ?Usulan, tipe?: string, url?: ?string}  $opsi
     * @return Collection<int, Notifikasi>
     */
    public function kirimKeBanyak(iterable $penerima, string $judul, string $pesan, array $opsi = []): Collection
    {
        return collect($penerima)
            ->filter()
            ->unique('id')
            ->map(fn (User $user) => $this->kirim($user, $judul, $pesan, $opsi))
            ->values();
    }

    /**
     * Kirim notifikasi ke seluruh pengguna dengan salah satu peran yang diberikan.
     *
     * @param  list<string>  $peran
     * @param  array{usulan?: ?Usulan, tipe?: string, url?: ?string}  $opsi
     * @return Collection<int, Notifikasi>
     */
    public function kirimKePeran(array $peran, string $judul, string $pesan, array $opsi = []): Collection
    {
        return $this->kirimKeBanyak(
            User::whereIn('role', $peran)->get(),
            $judul,
            $pesan,
            $opsi,
        );
    }
}
