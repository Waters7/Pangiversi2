<?php

namespace App\Models;

use App\Enums\BagianLaporan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu foto dokumentasi pada satu bagian laporan perjalanan dinas format
 * Kemenkes — tercetak pada halaman lampiran dokumennya.
 */
class LaporanDokumentasi extends Model
{
    protected $table = 'laporan_dokumentasi';

    protected $fillable = ['id_laporan', 'bagian', 'berkas'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bagian' => BagianLaporan::class];
    }

    /**
     * @return BelongsTo<LaporanPerjadin, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanPerjadin::class, 'id_laporan');
    }
}
