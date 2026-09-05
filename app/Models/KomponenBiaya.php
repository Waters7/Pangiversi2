<?php

namespace App\Models;

use Database\Factories\KomponenBiayaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KomponenBiaya extends Model
{
    /** @use HasFactory<KomponenBiayaFactory> */
    use HasFactory;

    protected $table = 'komponen_biaya';

    protected $fillable = [
        'nama',
        'satuan',
        'harga_satuan',
        'jenis',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga_satuan' => 'float',
            'is_aktif' => 'boolean',
        ];
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
            'sbm' => 'SBM (Standar Biaya Masukan)',
            'at_cost' => 'At Cost (Sesuai Bukti)',
        ];
    }

    public function getJenisLabelAttribute(): string
    {
        return self::jenisOptions()[$this->jenis] ?? '-';
    }
}
