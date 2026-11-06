<?php

namespace App\Models;

use Database\Factories\PengumumanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemberitahuan dari tim keuangan atau super administrator kepada seluruh
 * pengguna, tampil pada menu Pengumuman dan popup saat masuk selama masa
 * tayangnya.
 */
class Pengumuman extends Model
{
    /** @use HasFactory<PengumumanFactory> */
    use HasFactory;

    public const TAYANG = 'Tayang';

    public const TERJADWAL = 'Terjadwal';

    public const BERAKHIR = 'Berakhir';

    protected $table = 'pengumuman';

    protected $fillable = [
        'judul',
        'isi',
        'mulai_tayang',
        'selesai_tayang',
        'id_pembuat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mulai_tayang' => 'date',
            'selesai_tayang' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pembuat');
    }

    /**
     * Yang sedang dalam masa tayang. Tanggal selesai ikut terhitung, dan
     * tanpa tanggal selesai berarti tayang sampai dihapus.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeTayang(Builder $query): void
    {
        $query->whereDate('mulai_tayang', '<=', today())
            ->where(fn (Builder $q) => $q->whereNull('selesai_tayang')->orWhereDate('selesai_tayang', '>=', today()));
    }

    public function status(): string
    {
        return match (true) {
            $this->mulai_tayang->isFuture() => self::TERJADWAL,
            $this->selesai_tayang?->lt(today()) === true => self::BERAKHIR,
            default => self::TAYANG,
        };
    }

    public function badgeStatus(): string
    {
        return match ($this->status()) {
            self::TAYANG => 'bg-emerald-100 text-emerald-700',
            self::TERJADWAL => 'bg-blue-100 text-blue-700',
            default => 'bg-slate-100 text-slate-500',
        };
    }
}
