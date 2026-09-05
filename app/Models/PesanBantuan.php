<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pesan di dalam obrolan bantuan.
 */
class PesanBantuan extends Model
{
    protected $table = 'pesan_bantuan';

    protected $fillable = [
        'id_obrolan',
        'id_pengirim',
        'isi',
        'lampiran',
        'dibaca_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dibaca_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ObrolanBantuan, $this>
     */
    public function obrolan(): BelongsTo
    {
        return $this->belongsTo(ObrolanBantuan::class, 'id_obrolan');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengirim');
    }

    public function dariAdmin(): bool
    {
        return (bool) $this->pengirim?->isAdmin();
    }
}
