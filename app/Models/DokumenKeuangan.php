<?php

namespace App\Models;

use Database\Factories\DokumenKeuanganFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DokumenKeuangan extends Model
{
    /** @use HasFactory<DokumenKeuanganFactory> */
    use HasFactory;

    protected $table = 'dokumen_keuangan';

    protected $fillable = [
        'transfer_uang_muka',
        'transfer_sisa',
        'id_keuangan',
    ];

    public function keuangan()
    {
        return $this->belongsTo(Keuangan::class, 'id_keuangan');
    }
}
