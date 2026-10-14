<?php

namespace App\Models;

use Database\Factories\PaguAkunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagu — angka maksimal — satu kode akun dalam satu tahun anggaran,
 * beserta sumber dananya.
 *
 * Pagu tahun anggaran adalah jumlah pagu seluruh akunnya; lihat
 * TahunAnggaran::selaraskanPagu().
 */
class PaguAkun extends Model
{
    /** @use HasFactory<PaguAkunFactory> */
    use HasFactory;

    protected $table = 'pagu_akun';

    protected $fillable = [
        'id_tahun_anggaran',
        'id_akun_pembiayaan',
        'id_kategori_pembiayaan',
        'pagu',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pagu' => 'float'];
    }

    /**
     * @return BelongsTo<TahunAnggaran, $this>
     */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class, 'id_tahun_anggaran');
    }

    /**
     * @return BelongsTo<AkunPembiayaan, $this>
     */
    public function akun(): BelongsTo
    {
        return $this->belongsTo(AkunPembiayaan::class, 'id_akun_pembiayaan');
    }

    /**
     * Sumber dana (RM/BLU/LN) yang membiayai akun ini.
     *
     * @return BelongsTo<KategoriPembiayaan, $this>
     */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(KategoriPembiayaan::class, 'id_kategori_pembiayaan');
    }
}
