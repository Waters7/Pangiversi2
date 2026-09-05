<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengikut yang menyertai perjalanan dinas, dicantumkan pada butir 8 SPD.
 */
class SpdPengikut extends Model
{
    use HasFactory;

    protected $table = 'spd_pengikut';

    protected $fillable = ['id_spd', 'nama', 'tanggal_lahir', 'keterangan'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    /**
     * @return BelongsTo<SuratPerjalananDinas, $this>
     */
    public function spd(): BelongsTo
    {
        return $this->belongsTo(SuratPerjalananDinas::class, 'id_spd');
    }
}
