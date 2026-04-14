<?php

namespace App\Models;

use Database\Factories\RincianBiayaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RincianBiaya extends Model
{
    /** @use HasFactory<RincianBiayaFactory> */
    use HasFactory;

    protected $fillable = [
        'komponen',
        'volume',
        'satuan',
        'harga_satuan',
        'jumlah',
        'id_keuangan',
    ];

    public function keuangan()
    {
        return $this->belongsTo(Keuangan::class, 'id_keuangan');
    }
}
