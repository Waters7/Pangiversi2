<?php

namespace App\Models;

use Database\Factories\KeuanganFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Keuangan extends Model
{
    /** @use HasFactory<KeuanganFactory> */
    use HasFactory;

    protected $table = 'keuangan';

    protected $fillable = [
        'tanggal_transfer',
        'tanggal_pelunasan',
        'total',
        'uang_muka',
        'sisa',
        'status',
        'id_usulan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_transfer' => 'date',
            'tanggal_pelunasan' => 'date',
        ];
    }

    public function usulan()
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    public function rincianBiaya()
    {
        return $this->hasMany(RincianBiaya::class, 'id_keuangan');
    }

    public function dokumenKeuangan()
    {
        return $this->hasOne(DokumenKeuangan::class, 'id_keuangan');
    }

    /**
     * Hitung ulang total, uang muka (80%), dan sisa (20%) dari rincian biaya.
     */
    public function hitungTotal(): void
    {
        $total = $this->rincianBiaya()->sum('jumlah');
        $uangMuka = $total * 0.8;
        $sisa = $total - $uangMuka;

        $this->update([
            'total' => $total,
            'uang_muka' => $uangMuka,
            'sisa' => $sisa,
        ]);
    }
}
