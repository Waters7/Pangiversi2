<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali pengiriman data dashboard eksekutif ke aplikasi tujuan —
 * oleh penjadwal maupun ditekan manual dari menu Integrasi Data — beserta
 * hasilnya, supaya kegagalan terlihat tanpa membuka log peladen.
 */
class PengirimanIntegrasi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pengiriman_integrasi';

    public const PEMICU_JADWAL = 'jadwal';

    public const PEMICU_MANUAL = 'manual';

    public const BERHASIL = 'berhasil';

    public const GAGAL = 'gagal';

    protected $fillable = [
        'pemicu', 'id_user', 'tujuan', 'status', 'kode_http', 'pesan', 'tahun', 'ukuran_byte', 'durasi_ms', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function berhasil(): bool
    {
        return $this->status === self::BERHASIL;
    }

    public function labelPemicu(): string
    {
        return $this->pemicu === self::PEMICU_MANUAL ? 'Manual' : 'Jadwal';
    }
}
