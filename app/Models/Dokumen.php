<?php

namespace App\Models;

use Database\Factories\DokumenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dokumen extends Model
{
    /** @use HasFactory<DokumenFactory> */
    use HasFactory;

    protected $table = 'dokumen';

    protected $fillable = [
        'surat_tugas',
        'rundown',
        'dokumen_pendukung',
        'sppd',
        'boarding_pass',
        'nota_transportasi',
        'faktur',
        'kwintasi',
        'bill_hotel',
        'bill_hotel_no_transaksi',
        'bill_hotel_nominal',
        'laporan_hasil',
        'id_usulan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['bill_hotel_nominal' => 'float'];
    }

    public function usulan()
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }
}
