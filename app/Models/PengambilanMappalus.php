<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan pengambilan data satu pelaksana oleh Mappalus. Referensinya nomor
 * usulan, sehingga satu perjalanan hanya punya satu catatan.
 */
class PengambilanMappalus extends Model
{
    protected $table = 'pengambilan_mappalus';

    protected $fillable = [
        'referensi',
        'id_usulan',
        'id_nominatif',
        'tim_kerja',
        'kode_akun',
        'total_nominal',
        'keterangan',
        'sidik',
        'sidik_dikonfirmasi',
        'jumlah_diambil',
        'pertama_diambil_at',
        'diambil_at',
        'dikonfirmasi_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_nominal' => 'decimal:2',
            'jumlah_diambil' => 'integer',
            'pertama_diambil_at' => 'datetime',
            'diambil_at' => 'datetime',
            'dikonfirmasi_at' => 'datetime',
        ];
    }

    /** Mappalus pernah menyatakan sudah menyimpan data pelaksana ini. */
    public function pernahDikonfirmasi(): bool
    {
        return $this->sidik_dikonfirmasi !== null;
    }
}
