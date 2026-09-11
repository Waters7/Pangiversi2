<?php

namespace App\Models;

use App\Enums\ArahTiket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tiket perjalanan dinas — pergi atau pulang.
 */
class TiketPerjadin extends Model
{
    protected $table = 'tiket_perjadin';

    protected $fillable = [
        'id_usulan',
        'arah',
        'kota_asal',
        'kota_tujuan',
        'nomor_tiket',
        'kode_booking',
        'harga',
        'boarding_pass',
        'invoice',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arah' => ArahTiket::class,
            'harga' => 'float',
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
     * Tiket dianggap terisi bila rute, nomor, kode booking, harga, dan
     * boarding pass-nya sudah ada — sebagian saja belum cukup untuk
     * dipertanggungjawabkan.
     */
    public function lengkap(): bool
    {
        return filled($this->kota_asal)
            && filled($this->kota_tujuan)
            && filled($this->nomor_tiket)
            && filled($this->kode_booking)
            && $this->harga > 0
            && filled($this->boarding_pass)
            && filled($this->invoice);
    }

    public function rute(): string
    {
        return filled($this->kota_asal) && filled($this->kota_tujuan)
            ? $this->kota_asal.' → '.$this->kota_tujuan
            : '—';
    }
}
