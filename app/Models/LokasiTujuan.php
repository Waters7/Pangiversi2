<?php

namespace App\Models;

use Database\Factories\LokasiTujuanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LokasiTujuan extends Model
{
    /** @use HasFactory<LokasiTujuanFactory> */
    use HasFactory;

    protected $table = 'lokasi_tujuan';

    protected $fillable = [
        'nama',
        'provinsi',
        'jenis',
        'lintang',
        'bujur',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
            'lintang' => 'float',
            'bujur' => 'float',
        ];
    }

    /**
     * @return HasMany<Usulan, $this>
     */
    public function usulan(): HasMany
    {
        return $this->hasMany(Usulan::class, 'id_lokasi');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }

    /**
     * @return array<string, string>
     */
    public static function jenisOptions(): array
    {
        return [
            'dalam_kota' => 'Dalam Kota',
            'luar_kota' => 'Luar Kota',
            'luar_negeri' => 'Luar Negeri',
        ];
    }

    public function getJenisLabelAttribute(): string
    {
        return self::jenisOptions()[$this->jenis] ?? '-';
    }

    public function getNamaLengkapAttribute(): string
    {
        return $this->provinsi ? "{$this->nama}, {$this->provinsi}" : $this->nama;
    }
}
