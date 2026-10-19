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
        'spd_ditandatangani',
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
        'penginapan_ada',
        'penyelenggaraan_ada',
        'penyelenggaraan_termasuk_penginapan',
        'penyelenggaraan_nominal',
        'penyelenggaraan_invoice',
        'penyelenggaraan_bukti',
        'laporan_hasil',
        'id_usulan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bill_hotel_nominal' => 'float',
            'penginapan_ada' => 'boolean',
            'penyelenggaraan_ada' => 'boolean',
            'penyelenggaraan_termasuk_penginapan' => 'boolean',
            'penyelenggaraan_nominal' => 'float',
        ];
    }

    /**
     * Pelaksana menyatakan ada biaya penyelenggaraan yang ia bayar.
     */
    public function adaPenyelenggaraan(): bool
    {
        return $this->penyelenggaraan_ada === true;
    }

    /**
     * Penginapan sudah dibayar lewat biaya penyelenggaraan, sehingga bill
     * hotel tidak ditagih dan uang penginapan tidak dibayarkan lagi.
     */
    public function penginapanTermasukPenyelenggaraan(): bool
    {
        return $this->adaPenyelenggaraan() && $this->penyelenggaraan_termasuk_penginapan === true;
    }

    /**
     * Pelaksana menyatakan tidak ada biaya penginapan. Belum dijawab tidak
     * dihitung sebagai tidak ada.
     */
    public function tanpaPenginapan(): bool
    {
        return $this->penginapan_ada === false;
    }

    public function usulan()
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }
}
