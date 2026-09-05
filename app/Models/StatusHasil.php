<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Pilihan status hasil yang dicapai pada laporan perjalanan dinas.
 *
 * Master data, bukan enum: satuan kerja dapat menambah pilihannya sendiri
 * lewat menu Master Data tanpa menunggu aplikasi ditempatkan ulang.
 */
class StatusHasil extends Model
{
    protected $table = 'status_hasil';

    protected $fillable = ['nama', 'keterangan', 'urutan', 'is_aktif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['urutan' => 'integer', 'is_aktif' => 'boolean'];
    }

    /**
     * @return HasMany<LaporanPerjadin, $this>
     */
    public function laporan(): HasMany
    {
        return $this->hasMany(LaporanPerjadin::class, 'id_status_hasil');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /**
     * Pilihan yang ditawarkan pada formulir laporan.
     *
     * @return Collection<int, self>
     */
    public static function pilihan(): Collection
    {
        return self::aktif()->orderBy('urutan')->orderBy('nama')->get();
    }

    /**
     * Warna badge mengikuti maknanya, agar terbaca sekilas pada daftar.
     */
    public function getBadgeAttribute(): string
    {
        return match (true) {
            str_contains(mb_strtolower($this->nama), 'selesai dikerjakan') => 'bg-emerald-100 text-emerald-700',
            str_contains(mb_strtolower($this->nama), 'tidak selesai') => 'bg-red-100 text-red-700',
            str_contains(mb_strtolower($this->nama), 'tindak lanjut') => 'bg-amber-100 text-amber-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
