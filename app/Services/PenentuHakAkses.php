<?php

namespace App\Services;

use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Collection;

/**
 * Menentukan kemampuan sebuah kode peran.
 *
 * Sumbernya tabel peran bila kode itu sudah dikelola dari Administrasi
 * Sistem; bila belum, hak akses bawaan enum PeranPengguna yang berlaku.
 * Tabel dibaca sekali per permintaan dan dihafal — model Peran dan
 * HakAksesPeran memanggil lupakan() setiap kali berubah.
 */
class PenentuHakAkses
{
    /** @var Collection<string, Peran>|null */
    private ?Collection $peran = null;

    /**
     * @return list<Kemampuan>
     */
    public function kemampuan(string $kode): array
    {
        // Super administrator tidak pernah dikurangi, dari sumber mana pun.
        if ($kode === PeranPengguna::SuperAdministrator->value) {
            return Kemampuan::cases();
        }

        $peran = $this->semua()->get($kode);

        return $peran ? $peran->kemampuan() : PeranPengguna::dari($kode)->kemampuan();
    }

    public function punya(string $kode, Kemampuan $kemampuan): bool
    {
        return in_array($kemampuan, $this->kemampuan($kode), true);
    }

    /**
     * Nama peran yang tampil: dari tabel bila ada, selebihnya label enum.
     */
    public function label(string $kode): string
    {
        return $this->semua()->get($kode)?->nama ?? PeranPengguna::dari($kode)->label();
    }

    public function lupakan(): void
    {
        $this->peran = null;
    }

    /** @return Collection<string, Peran> */
    private function semua(): Collection
    {
        return $this->peran ??= Peran::with('hakAkses')->get()->keyBy('kode');
    }
}
