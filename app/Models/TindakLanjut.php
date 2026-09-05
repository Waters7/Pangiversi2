<?php

namespace App\Models;

use App\Enums\StatusTindakLanjut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu rencana tindak lanjut hasil perjalanan dinas.
 */
class TindakLanjut extends Model
{
    protected $table = 'tindak_lanjut';

    protected $fillable = [
        'id_laporan',
        'urutan',
        'uraian',
        'penanggung_jawab',
        'target_selesai',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'target_selesai' => 'date',
            'status' => StatusTindakLanjut::class,
        ];
    }

    /**
     * @return BelongsTo<LaporanPerjadin, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanPerjadin::class, 'id_laporan');
    }

    /**
     * Belum tuntas dan tanggal targetnya sudah lewat.
     */
    public function terlambat(): bool
    {
        return $this->status !== StatusTindakLanjut::Selesai
            && $this->target_selesai !== null
            && $this->target_selesai->isPast();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeBelumSelesai(Builder $query): void
    {
        $query->where('status', '!=', StatusTindakLanjut::Selesai->value);
    }
}
