<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pelaksana pada sebuah SPD.
 *
 * Nomor surat melekat pada pelaksana, bukan pada berkasnya, karena lembar
 * SPD terbit per orang meski rencana perjalanannya sama.
 */
class SpdPelaksana extends Model
{
    use HasFactory;

    protected $table = 'spd_pelaksana';

    /**
     * Klasifikasi arsip pada nomor SPD Poltekkes Kemenkes Manado.
     *
     * Pola lengkapnya KU.02.04/F.XXX.8/{nomor urut}/{tahun surat}. Pelaksana
     * hanya mengetik nomor urutnya sesuai buku agenda; awalan dan tahunnya
     * disusun sistem agar tidak ada surat yang salah format.
     */
    public const AWALAN_NOMOR = 'KU.02.04/F.XXX.8/';

    protected $fillable = [
        'id_spd',
        'id_user',
        'urutan',
        'nomor_surat',
        'nama',
        'nip',
        'pangkat_golongan',
        'jabatan_instansi',
        'tingkat_biaya',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    /**
     * @return BelongsTo<SuratPerjalananDinas, $this>
     */
    public function spd(): BelongsTo
    {
        return $this->belongsTo(SuratPerjalananDinas::class, 'id_spd');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Susun nomor surat lengkap dari nomor urut dan tahun suratnya.
     */
    public static function rakitNomor(string $urut, int $tahun): string
    {
        return self::AWALAN_NOMOR.trim($urut).'/'.$tahun;
    }

    /**
     * Ambil kembali nomor urut dari sebuah nomor surat lengkap.
     *
     * Diambil dari segmen kedua terakhir, bukan dengan memotong awalan:
     * surat lama terbit dengan awalan F.XXX.8 tanpa ".1", dan nomornya tidak
     * boleh berubah hanya karena klasifikasi arsipnya kini disempurnakan.
     */
    public static function nomorUrut(?string $lengkap): string
    {
        $bagian = explode('/', (string) $lengkap);

        return count($bagian) >= 2 ? trim($bagian[count($bagian) - 2]) : '';
    }
}
