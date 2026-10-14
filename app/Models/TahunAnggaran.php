<?php

namespace App\Models;

use App\Services\RingkasanEksekutif;
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
     * Pagu tiap kode akun pada tahun ini.
     *
     * @return HasMany<PaguAkun, $this>
     */
    public function paguAkun(): HasMany
    {
        return $this->hasMany(PaguAkun::class, 'id_tahun_anggaran');
    }

    /** Apakah pagunya sudah dirinci per kode akun. */
    public function dirinciPerAkun(): bool
    {
        return $this->paguAkun()->exists();
    }

    /**
     * Pagu tahun anggaran yang sudah dirinci per akun adalah jumlah pagu
     * seluruh akunnya, supaya keduanya tidak pernah berselisih.
     */
    public function selaraskanPagu(): void
    {
        if ($this->dirinciPerAkun()) {
            $this->update(['pagu' => (float) $this->paguAkun()->sum('pagu')]);
        }
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
     * Total realisasi biaya tahun ini — hitungan yang sama dengan dashboard
     * eksekutif: usulan disetujui atau selesai yang berangkat pada tahunnya.
     */
    public function getRealisasiAttribute(): float
    {
        return app(RingkasanEksekutif::class)->totalRealisasi($this->tahun);
    }

    public function getSisaPaguAttribute(): float
    {
        return $this->pagu - $this->realisasi;
    }
}
