<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usulan extends Model
{
    use HasFactory;

    protected $table = 'usulan';

    public function getRouteKeyName(): string
    {
        return 'no_usulan';
    }

    protected $fillable = [
        'no_usulan',
        'no_tugas',
        'status',
        'lokasi',
        'instansi',
        'tanggal_mulai',
        'tanggal_selesai',
        'uraian',
        'catatan',
        'id_user',
        'id_kegiatan',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan');
    }

    public function dokumen()
    {
        return $this->hasMany(Dokumen::class, 'id_usulan');
    }

    public function keuangan()
    {
        return $this->hasOne(Keuangan::class, 'id_usulan');
    }

    public function getStatusBadgeAttribute()
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'diajukan' => 'bg-blue-100 text-blue-800',
            'menunggu' => 'bg-yellow-100 text-yellow-800',
            'disetujui' => 'bg-green-100 text-green-800',
            'ditolak' => 'bg-red-100 text-red-800',
            'selesai' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'diajukan' => 'Diajukan',
            'menunggu' => 'Menunggu',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'selesai' => 'Selesai',
            default => 'Unknown',
        };
    }

    public function getPeriodeAttribute()
    {
        return date('d M Y', strtotime($this->tanggal_mulai)).' — '.date('d M Y', strtotime($this->tanggal_selesai));
    }

    public function getTanggalMulaiFormattedAttribute()
    {
        return date('d M Y', strtotime($this->tanggal_mulai));
    }

    public function getTanggalSelesaiFormattedAttribute()
    {
        return date('d M Y', strtotime($this->tanggal_selesai));
    }

    public function getDurasiAttribute()
    {
        $start = strtotime($this->tanggal_mulai);
        $end = strtotime($this->tanggal_selesai);
        $diff = $end - $start;

        return floor($diff / (60 * 60 * 24)) + 1; // Durasi dalam hari
    }

    /**
     * Cek apakah pembayaran sudah lunas dan semua dokumen pertanggungjawaban telah diunggah.
     * Jika ya, ubah status menjadi 'selesai'.
     */
    public function checkCompletion(): bool
    {
        if ($this->status !== 'disetujui') {
            return false;
        }

        // Cek keuangan sudah lunas
        $keuangan = $this->keuangan;
        if (! $keuangan || $keuangan->status !== 'lunas') {
            return false;
        }

        // Cek semua dokumen pertanggungjawaban sudah diunggah
        $dokumen = $this->dokumen()->latest('id')->first();
        if (! $dokumen) {
            return false;
        }

        $requiredFields = ['sppd', 'boarding_pass', 'faktur', 'kwintasi', 'bill_hotel', 'laporan_hasil'];
        foreach ($requiredFields as $field) {
            if (empty($dokumen->$field)) {
                return false;
            }
        }

        $this->update(['status' => 'selesai']);

        return true;
    }
}
