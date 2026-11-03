<?php

namespace App\Models;

use Database\Factories\TimKerjaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tim kerja pengelola anggaran — misalnya Tim OSDM atau Jurusan
 * Keperawatan — yang memegang pagu pada kode akun tertentu.
 *
 * Berbeda dengan unit kerja pegawai: satu tim kerja dapat membiayai
 * perjalanan pegawai dari unit mana pun.
 */
class TimKerja extends Model
{
    /** @use HasFactory<TimKerjaFactory> */
    use HasFactory;

    protected $table = 'tim_kerja';

    protected $fillable = [
        'nama',
        'kewadiran',
        'urutan',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PaguTimKerja, $this>
     */
    public function pagu(): HasMany
    {
        return $this->hasMany(PaguTimKerja::class, 'id_tim_kerja');
    }
}
