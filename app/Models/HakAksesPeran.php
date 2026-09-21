<?php

namespace App\Models;

use App\Services\PenentuHakAkses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kemampuan (nilai enum Kemampuan) yang diberikan kepada sebuah peran.
 *
 * @property int $id_peran
 * @property string $kemampuan
 */
class HakAksesPeran extends Model
{
    protected $table = 'hak_akses_peran';

    protected $fillable = ['id_peran', 'kemampuan'];

    protected static function booted(): void
    {
        $lupakan = fn () => app(PenentuHakAkses::class)->lupakan();
        static::saved($lupakan);
        static::deleted($lupakan);
    }

    /** @return BelongsTo<Peran, $this> */
    public function peran(): BelongsTo
    {
        return $this->belongsTo(Peran::class, 'id_peran');
    }
}
