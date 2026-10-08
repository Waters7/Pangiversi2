<?php

namespace App\Models;

use Database\Factories\CatatanPenyimpananFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Hasil pengukuran ukuran aplikasi pada satu hari.
 *
 * Rinciannya menyimpan ukuran per kelompok (unggahan, basis data, log,
 * pustaka, dan seterusnya), per folder unggahan, per tabel, serta berkas
 * terbesar — cukup untuk menampilkan halaman Pemantauan Server tanpa
 * menghitung ulang seluruh folder.
 */
class CatatanPenyimpanan extends Model
{
    /** @use HasFactory<CatatanPenyimpananFactory> */
    use HasFactory;

    protected $table = 'catatan_penyimpanan';

    protected $fillable = [
        'tanggal',
        'total_byte',
        'unggahan_byte',
        'basis_data_byte',
        'rincian',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total_byte' => 'integer',
            'unggahan_byte' => 'integer',
            'basis_data_byte' => 'integer',
            'rincian' => 'array',
        ];
    }

    /**
     * Ukuran byte dalam satuan yang mudah dibaca, misalnya "1,4 GB".
     */
    public static function ukuranTerbaca(int|float|null $byte): string
    {
        $byte = max(0, (float) $byte);
        $satuan = ['B', 'KB', 'MB', 'GB', 'TB'];
        $tingkat = 0;

        while ($byte >= 1024 && $tingkat < count($satuan) - 1) {
            $byte /= 1024;
            $tingkat++;
        }

        return number_format($byte, $tingkat === 0 || $byte >= 100 ? 0 : 1, ',', '.').' '.$satuan[$tingkat];
    }
}
