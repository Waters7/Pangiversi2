<?php

namespace App\Models;

use App\Enums\LevelPersetujuan;
use Database\Factories\PersetujuanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Persetujuan extends Model
{
    /** @use HasFactory<PersetujuanFactory> */
    use HasFactory;

    protected $table = 'persetujuan';

    public const KEPUTUSAN_SETUJU = 'setuju';

    public const KEPUTUSAN_TOLAK = 'tolak';

    public const KEPUTUSAN_REVISI = 'revisi';

    protected $fillable = [
        'id_usulan',
        'id_approver',
        'level',
        'peran',
        'keputusan',
        'catatan',
        'waktu_keputusan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => LevelPersetujuan::class,
            'waktu_keputusan' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_approver');
    }

    /**
     * @return array<string, string>
     */
    public static function keputusanOptions(): array
    {
        return [
            self::KEPUTUSAN_SETUJU => 'Disetujui',
            self::KEPUTUSAN_TOLAK => 'Ditolak',
            self::KEPUTUSAN_REVISI => 'Diminta Revisi',
        ];
    }

    public function getKeputusanLabelAttribute(): string
    {
        return self::keputusanOptions()[$this->keputusan] ?? '-';
    }

    public function getKeputusanBadgeAttribute(): string
    {
        return match ($this->keputusan) {
            self::KEPUTUSAN_SETUJU => 'bg-emerald-100 text-emerald-700',
            self::KEPUTUSAN_TOLAK => 'bg-red-100 text-red-700',
            self::KEPUTUSAN_REVISI => 'bg-amber-100 text-amber-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
