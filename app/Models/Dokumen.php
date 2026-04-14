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
        'faktur',
        'kwintasi',
        'bill_hotel',
        'laporan_hasil',
        'id_usulan',
    ];

    public function usulan()
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }
}
