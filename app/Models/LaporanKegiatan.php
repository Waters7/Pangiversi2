<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu uraian kegiatan yang dilakukan selama perjalanan dinas.
 */
class LaporanKegiatan extends Model
{
    protected $table = 'laporan_kegiatan';

    protected $fillable = ['id_laporan', 'urutan', 'tanggal', 'tempat', 'uraian'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['urutan' => 'integer', 'tanggal' => 'date'];
    }

    /**
     * @return BelongsTo<LaporanPerjadin, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanPerjadin::class, 'id_laporan');
    }
}
