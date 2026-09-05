<?php

namespace App\Models;

use Database\Factories\NotifikasiFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notifikasi extends Model
{
    /** @use HasFactory<NotifikasiFactory> */
    use HasFactory;

    protected $table = 'notifikasi';

    public const TIPE_INFO = 'info';

    public const TIPE_SUKSES = 'sukses';

    public const TIPE_PERINGATAN = 'peringatan';

    public const TIPE_BAHAYA = 'bahaya';

    protected $fillable = [
        'id_user',
        'id_usulan',
        'judul',
        'pesan',
        'tipe',
        'url',
        'dibaca_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dibaca_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeBelumDibaca(Builder $query): void
    {
        $query->whereNull('dibaca_at');
    }

    public function tandaiDibaca(): void
    {
        if ($this->dibaca_at === null) {
            $this->update(['dibaca_at' => now()]);
        }
    }

    public function getSudahDibacaAttribute(): bool
    {
        return $this->dibaca_at !== null;
    }

    /**
     * Kelas warna ikon berdasarkan tingkat kepentingan notifikasi.
     */
    public function getTipeBadgeAttribute(): string
    {
        return match ($this->tipe) {
            self::TIPE_SUKSES => 'bg-emerald-100 text-emerald-600',
            self::TIPE_PERINGATAN => 'bg-amber-100 text-amber-600',
            self::TIPE_BAHAYA => 'bg-red-100 text-red-600',
            default => 'bg-blue-100 text-blue-600',
        };
    }
}
