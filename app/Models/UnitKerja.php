<?php

namespace App\Models;

use Database\Factories\UnitKerjaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    /** @use HasFactory<UnitKerjaFactory> */
    use HasFactory;

    protected $table = 'unit_kerja';

    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function pegawai(): HasMany
    {
        return $this->hasMany(User::class, 'id_unit');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_aktif', true);
    }
}
