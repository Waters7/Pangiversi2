<?php

namespace App\Models;

use App\Enums\KategoriBiaya;
use Database\Factories\RincianBiayaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RincianBiaya extends Model
{
    /** @use HasFactory<RincianBiayaFactory> */
    use HasFactory;

    protected $fillable = [
        'kategori',
        'komponen',
        'volume',
        'satuan',
        'harga_satuan',
        'jumlah',
        'keterangan',
        'sumber',
        'kunci_sumber',
        'divalidasi_at',
        'id_validator',
        'id_keuangan',
    ];

    /** Baris yang diketik langsung tim keuangan. */
    public const SUMBER_KEUANGAN = 'keuangan';

    /** Baris yang lahir dari nominal pada berkas pertanggungjawaban. */
    public const SUMBER_DOKUMEN = 'dokumen';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KategoriBiaya::class,
            'harga_satuan' => 'float',
            'jumlah' => 'float',
            'divalidasi_at' => 'datetime',
        ];
    }

    /**
     * Berasal dari nominal yang diketik pelaksana, bukan dari tim keuangan.
     */
    public function dariDokumen(): bool
    {
        return $this->sumber === self::SUMBER_DOKUMEN;
    }

    /**
     * Sudah diperiksa tim keuangan.
     *
     * Baris yang diketik tim keuangan sendiri terhitung sah sejak dibuat;
     * yang perlu diperiksa hanyalah angka yang datang dari pelaksana.
     */
    public function sudahDivalidasi(): bool
    {
        return ! $this->dariDokumen() || $this->divalidasi_at !== null;
    }

    /**
     * @return BelongsTo<Keuangan, $this>
     */
    public function keuangan(): BelongsTo
    {
        return $this->belongsTo(Keuangan::class, 'id_keuangan');
    }

    /**
     * Uraian sebagaimana tercetak pada dokumen rincian resmi,
     * misalnya "2 Hari x Rp 370.000".
     */
    public function getUraianAttribute(): string
    {
        if ($this->kategori === KategoriBiaya::UangHarian && $this->volume > 0) {
            return "{$this->volume} {$this->satuan} x Rp ".number_format($this->harga_satuan, 0, ',', '.');
        }

        return $this->keterangan ?: $this->komponen;
    }
}
