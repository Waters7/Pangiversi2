<?php

namespace App\Models;

use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Services\PenentuHakAkses;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Peran pengguna yang dikelola dari Administrasi Sistem.
 *
 * Peran bawaan (dari enum PeranPengguna) disalin ke tabel ini saat halaman
 * Peran & Hak Akses pertama kali dibuka, supaya hak aksesnya dapat disunting;
 * peran baru ditambahkan super administrator. Peran yang belum ada di tabel
 * tetap memakai hak akses bawaan enumnya.
 *
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property ?string $keterangan
 * @property bool $bawaan
 */
class Peran extends Model
{
    protected $table = 'peran';

    protected $fillable = ['kode', 'nama', 'keterangan', 'bawaan'];

    protected function casts(): array
    {
        return ['bawaan' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Hak akses dihafal per permintaan; perubahan apa pun membuangnya.
        $lupakan = fn () => app(PenentuHakAkses::class)->lupakan();
        static::saved($lupakan);
        static::deleted($lupakan);
    }

    /** @return HasMany<HakAksesPeran, $this> */
    public function hakAkses(): HasMany
    {
        return $this->hasMany(HakAksesPeran::class, 'id_peran');
    }

    /** @return HasMany<User, $this> */
    public function pengguna(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'kode');
    }

    /**
     * Enum bawaan yang menjadi asal peran ini; null untuk peran buatan.
     */
    public function enum(): ?PeranPengguna
    {
        return PeranPengguna::tryFrom($this->kode);
    }

    /**
     * Super administrator memegang seluruh kemampuan dan tidak dapat
     * dikurangi — supaya tidak ada jalan mengunci diri sendiri dari
     * pengaturan hak akses.
     */
    public function terkunci(): bool
    {
        return $this->kode === PeranPengguna::SuperAdministrator->value;
    }

    /**
     * Kemampuan yang dimiliki peran ini menurut tabel hak akses.
     *
     * @return list<Kemampuan>
     */
    public function kemampuan(): array
    {
        if ($this->terkunci()) {
            return Kemampuan::cases();
        }

        return $this->hakAkses
            ->map(fn (HakAksesPeran $hak) => Kemampuan::tryFrom($hak->kemampuan))
            ->filter()
            ->values()
            ->all();
    }

    public function punya(Kemampuan $kemampuan): bool
    {
        return in_array($kemampuan, $this->kemampuan(), true);
    }

    /**
     * Ganti seluruh hak akses peran ini dengan daftar yang diberikan.
     *
     * @param  list<Kemampuan>  $daftar
     */
    public function aturHakAkses(array $daftar): void
    {
        $this->hakAkses()->delete();
        $this->hakAkses()->createMany(
            array_map(fn (Kemampuan $k) => ['kemampuan' => $k->value], array_values(array_unique($daftar, SORT_REGULAR)))
        );
        $this->unsetRelation('hakAkses');
        app(PenentuHakAkses::class)->lupakan();
    }

    /**
     * Pastikan setiap peran bawaan ada di tabel, lengkap dengan hak akses
     * bawaannya bila belum pernah diatur. Peran yang sudah ada tidak diubah.
     */
    public static function sinkronBawaan(): void
    {
        foreach (PeranPengguna::cases() as $enum) {
            $peran = static::firstOrCreate(
                ['kode' => $enum->value],
                ['nama' => $enum->label(), 'keterangan' => $enum->keterangan(), 'bawaan' => true],
            );

            if ($peran->wasRecentlyCreated || $peran->hakAkses()->doesntExist()) {
                $peran->aturHakAkses($enum->kemampuan());
            }
        }
    }

    /**
     * Kode peran dari namanya: huruf kecil dan garis bawah, gaya yang sama
     * dengan peran bawaan (dosen_tendik, tim_keuangan).
     */
    public static function kodeDari(string $nama): string
    {
        return Str::of($nama)->slug('_')->limit(60, '')->toString();
    }

    /**
     * Pilihan peran untuk formulir: peran dari tabel, ditambah peran bawaan
     * yang belum disalin ke tabel, dengan urutan enumnya.
     *
     * @return array<string, string>
     */
    public static function pilihan(): array
    {
        $tersimpan = static::orderBy('bawaan', 'desc')->orderBy('nama')->get()->keyBy('kode');
        $pilihan = [];

        foreach (PeranPengguna::cases() as $enum) {
            $pilihan[$enum->value] = $tersimpan[$enum->value]->nama ?? $enum->label();
        }

        foreach ($tersimpan as $kode => $peran) {
            $pilihan[$kode] ??= $peran->nama;
        }

        return $pilihan;
    }

    /** @return Collection<int, static> */
    public static function urut(): Collection
    {
        $urutanBawaan = array_flip(array_map(fn (PeranPengguna $p) => $p->value, PeranPengguna::cases()));

        return static::with('hakAkses')->withCount('pengguna')->get()
            ->sortBy(fn (self $peran) => [$peran->bawaan ? 0 : 1, $urutanBawaan[$peran->kode] ?? 99, $peran->nama])
            ->values();
    }
}
