<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Catatan satu permintaan yang masuk ke API bertoken — diterima, ditolak
 * karena tokennya keliru, atau tertutup karena token belum dipasang —
 * supaya administrator dapat memantau siapa memanggil apa dan dengan
 * token yang mana, tanpa tokennya sendiri pernah tersimpan utuh.
 */
class LogApi extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'log_api';

    public const DITERIMA = 'diterima';

    public const DITOLAK = 'ditolak';

    public const TERTUTUP = 'tertutup';

    /** Catatan lebih tua dari ini dibersihkan penjadwal. */
    public const SIMPAN_HARI = 90;

    protected $fillable = [
        'ip', 'metode', 'jalur', 'hasil', 'token_tersamar', 'user_agent', 'durasi_ms', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function catat(Request $request, string $hasil, ?string $token, ?float $mulai = null): self
    {
        return self::create([
            'ip' => (string) $request->ip(),
            'metode' => $request->method(),
            'jalur' => '/'.ltrim($request->path(), '/'),
            'hasil' => $hasil,
            'token_tersamar' => self::samarkan($token),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'durasi_ms' => $mulai === null ? null : (int) round((microtime(true) - $mulai) * 1000),
            'created_at' => now(),
        ]);
    }

    /**
     * Ujung-ujung token saja — cukup untuk mengenali token mana yang
     * dipakai, tidak cukup untuk memakainya.
     */
    public static function samarkan(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        if (mb_strlen($token) < 12) {
            return str_repeat('•', mb_strlen($token));
        }

        return mb_substr($token, 0, 6).'…'.mb_substr($token, -4);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSejak(Builder $query, \DateTimeInterface $sejak): void
    {
        $query->where('created_at', '>=', $sejak);
    }

    public function labelHasil(): string
    {
        return match ($this->hasil) {
            self::DITERIMA => 'Diterima',
            self::DITOLAK => 'Token ditolak',
            default => 'API tertutup',
        };
    }

    public function badgeHasil(): string
    {
        return match ($this->hasil) {
            self::DITERIMA => 'bg-emerald-100 text-emerald-700',
            self::DITOLAK => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
