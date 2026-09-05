<?php

namespace App\Models;

use Database\Factories\TahunAnggaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAnggaran extends Model
{
    /** @use HasFactory<TahunAnggaranFactory> */
    use HasFactory;

    protected $table = 'tahun_anggaran';

    protected $fillable = [
        'tahun',
        'pagu',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'pagu' => 'float',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Usulan, $this>
     */
    public function usulan(): HasMany
    {
        return $this->hasMany(Usulan::class, 'id_tahun_anggaran');
    }

    /**
     * Tahun anggaran yang sedang aktif, dengan fallback ke tahun berjalan.
     */
    public static function aktif(): ?self
    {
        return self::where('is_aktif', true)->first()
            ?? self::where('tahun', now()->year)->first();
    }

    /**
     * Jadikan tahun ini satu-satunya tahun anggaran yang aktif.
     */
    public function aktifkan(): void
    {
        self::where('id', '!=', $this->id)->update(['is_aktif' => false]);

        $this->update(['is_aktif' => true]);
    }

    /**
     * Total realisasi biaya seluruh usulan pada tahun anggaran ini.
     */
    public function getRealisasiAttribute(): float
    {
        return (float) Keuangan::whereHas(
            'usulan',
            fn ($query) => $query->where('id_tahun_anggaran', $this->id)
        )->sum('total');
    }

    public function getSisaPaguAttribute(): float
    {
        return $this->pagu - $this->realisasi;
    }
}
