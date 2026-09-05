<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mata anggaran yang dibebani sebuah perjalanan dinas.
 *
 * Dipisahkan dari [KategoriPembiayaan] yang menyatakan sumber dananya
 * (RM/BLU/LN): satu sumber dana dapat membebani beberapa akun.
 */
class AkunPembiayaan extends Model
{
    protected $table = 'akun_pembiayaan';

    protected $fillable = ['kode', 'nama', 'keterangan', 'urutan', 'is_aktif'];

    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_aktif' => 'boolean'];
    }

    public function nominatif(): HasMany
    {
        return $this->hasMany(DaftarNominatif::class, 'id_akun_pembiayaan');
    }

    public function scopeAktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->kode} — {$this->nama}";
    }

    /**
     * Pilihan untuk dropdown, hanya yang masih aktif.
     *
     * @return array<int, string>
     */
    public static function pilihan(): array
    {
        return self::aktif()
            ->orderBy('urutan')
            ->get()
            ->mapWithKeys(fn (self $akun) => [$akun->id => $akun->label])
            ->all();
    }
}
