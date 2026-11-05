<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan kiriman satu pelaksana ke Mappalus. Referensinya nomor usulan, yang
 * juga dikirim, sehingga satu perjalanan hanya punya satu catatan.
 */
class PengirimanMappalus extends Model
{
    protected $table = 'pengiriman_mappalus';

    protected $fillable = [
        'referensi',
        'id_usulan',
        'id_nominatif',
        'tim_kerja',
        'kode_akun',
        'total_nominal',
        'keterangan',
        'sidik',
        'sidik_diterima',
        'percobaan',
        'kode_http',
        'pesan',
        'dikirim_at',
        'diterima_at',
        'id_user',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_nominal' => 'decimal:2',
            'percobaan' => 'integer',
            'kode_http' => 'integer',
            'dikirim_at' => 'datetime',
            'diterima_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /** Mappalus pernah menerima data pelaksana ini. */
    public function pernahDiterima(): bool
    {
        return $this->sidik_diterima !== null;
    }
}
