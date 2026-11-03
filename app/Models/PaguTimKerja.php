<?php

namespace App\Models;

use Database\Factories\PaguTimKerjaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pagu satu tim kerja pada satu kode akun dan sumber dana dalam satu tahun
 * anggaran. Inilah yang menentukan tim kerja mana yang boleh dibebani
 * sebuah daftar nominatif berakun itu.
 */
class PaguTimKerja extends Model
{
    /** @use HasFactory<PaguTimKerjaFactory> */
    use HasFactory;

    protected $table = 'pagu_tim_kerja';

    protected $fillable = [
        'id_tahun_anggaran',
        'id_akun_pembiayaan',
        'id_kategori_pembiayaan',
        'id_tim_kerja',
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
     * @return BelongsTo<KategoriPembiayaan, $this>
     */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(KategoriPembiayaan::class, 'id_kategori_pembiayaan');
    }

    /**
     * @return BelongsTo<TimKerja, $this>
     */
    public function timKerja(): BelongsTo
    {
        return $this->belongsTo(TimKerja::class, 'id_tim_kerja');
    }
}
