<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris pada Daftar Pengeluaran Riil.
 */
class RincianDaftarRiil extends Model
{
    protected $table = 'rincian_daftar_riil';

    /** Baris yang diketik tim keuangan. */
    public const SUMBER_MANUAL = 'manual';

    /** Baris yang lahir dari nota transportasi pelaksana. */
    public const SUMBER_DOKUMEN = 'dokumen';

    protected $fillable = [
        'id_daftar_riil',
        'urutan',
        'uraian',
        'nominal',
        'nominal_nota',
        'sumber',
        'kunci_sumber',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['urutan' => 'integer', 'nominal' => 'float', 'nominal_nota' => 'float'];
    }

    /**
     * @return BelongsTo<DaftarRiil, $this>
     */
    public function daftarRiil(): BelongsTo
    {
        return $this->belongsTo(DaftarRiil::class, 'id_daftar_riil');
    }

    public function dariDokumen(): bool
    {
        return $this->sumber === self::SUMBER_DOKUMEN;
    }

    /**
     * Nominal nota pelaksana yang sudah dikoreksi tim keuangan.
     */
    public function dikoreksi(): bool
    {
        return $this->dariDokumen()
            && $this->nominal_nota !== null
            && (float) $this->nominal_nota !== (float) $this->nominal;
    }
}
