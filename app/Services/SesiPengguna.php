<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siapa yang sedang memakai aplikasi saat ini.
 *
 * Jawabannya dibaca dari tabel sesi, bukan dari penanda yang ditulis sendiri
 * saat masuk: sesi hilang sendiri ketika pengguna keluar atau kedaluwarsa,
 * sehingga angkanya tidak pernah menggantung karena seseorang menutup
 * peramban tanpa menekan tombol keluar.
 *
 * Hanya berlaku bila sesi disimpan di basis data. Pada penyimpanan lain
 * jawabannya kosong — lebih baik diam daripada menyebut angka karangan.
 */
class SesiPengguna
{
    /** Sesi dianggap masih hidup bila ada kegiatan dalam rentang ini. */
    public const MENIT_AKTIF = 15;

    public function tersedia(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Id pengguna yang sesinya masih hidup.
     *
     * @return Collection<int, int>
     */
    public function idAktif(): Collection
    {
        if (! $this->tersedia()) {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(self::MENIT_AKTIF)->getTimestamp())
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id);
    }

    public function jumlahAktif(): int
    {
        return $this->idAktif()->count();
    }

    /**
     * Pengguna yang sedang aktif, lengkap dengan identitasnya.
     *
     * @return Collection<int, User>
     */
    public function penggunaAktif(): Collection
    {
        $id = $this->idAktif();

        if ($id->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $id)->orderBy('nama')->get();
    }

    /**
     * Berapa banyak yang sedang aktif menurut perannya.
     *
     * @return Collection<string, int>
     */
    public function aktifPerPeran(): Collection
    {
        return $this->penggunaAktif()->countBy('role');
    }
}
