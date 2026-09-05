<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Sumber dana yang membiayai sebuah perjalanan dinas.
 *
 * Daftar nominatif dipisah per kategori ini — RM, BLU, LN, dan seterusnya —
 * mengikuti lembar-lembar pada berkas rekap yang disusun tim keuangan.
 */
class KategoriPembiayaan extends Model
{
    protected $table = 'kategori_pembiayaan';

    protected $fillable = ['kode', 'nama', 'keterangan', 'urutan', 'is_aktif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_aktif' => 'boolean'];
    }

    /**
     * @return HasMany<Usulan, $this>
     */
    public function usulan(): HasMany
    {
        return $this->hasMany(Usulan::class, 'id_kategori_pembiayaan');
    }

    /**
     * @return HasMany<DaftarNominatif, $this>
     */
    public function nominatif(): HasMany
    {
        return $this->hasMany(DaftarNominatif::class, 'id_kategori_pembiayaan');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /**
     * @return Collection<int, self>
     */
    public static function pilihan(): Collection
    {
        return self::aktif()->orderBy('urutan')->orderBy('kode')->get();
    }

    public function getLabelAttribute(): string
    {
        return $this->kode.' — '.$this->nama;
    }
}
