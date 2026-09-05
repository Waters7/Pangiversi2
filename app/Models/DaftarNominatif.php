<?php

namespace App\Models;

use App\Services\TautanVerifikasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Daftar nominatif pembayaran perjalanan dinas, terbit per surat tugas.
 *
 * Baris-barisnya sengaja tidak disimpan di sini: lihat PenyusunNominatif,
 * yang menurunkannya dari usulan berikut rincian biayanya. Yang disimpan
 * hanya keputusan atas daftar itu — tanda tangan PPK dan pengiriman ke
 * Tim SDM.
 */
class DaftarNominatif extends Model
{
    protected $table = 'daftar_nominatif';

    protected $fillable = [
        'no_tugas',
        'tanggal_tugas',
        'id_kategori_pembiayaan',
        'id_akun_pembiayaan',
        'id_ppk',
        'ditandatangani_at',
        'dikirim_at',
        'kode_verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_tugas' => 'date',
            'ditandatangani_at' => 'datetime',
            'dikirim_at' => 'datetime',
        ];
    }

    public function ppk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_ppk');
    }

    public function kategoriPembiayaan(): BelongsTo
    {
        return $this->belongsTo(KategoriPembiayaan::class, 'id_kategori_pembiayaan');
    }

    public function akunPembiayaan(): BelongsTo
    {
        return $this->belongsTo(AkunPembiayaan::class, 'id_akun_pembiayaan');
    }

    public function sudahDitandatangani(): bool
    {
        return $this->ditandatangani_at !== null;
    }

    /**
     * Alamat halaman verifikasi publik yang ditanam di dalam QR tanda
     * tangan PPK pada cetakan daftar nominatif.
     */
    public function urlVerifikasi(): ?string
    {
        return app(TautanVerifikasi::class)->untuk($this->kode_verifikasi);
    }

    /**
     * Terbitkan kode verifikasi bila daftar ini belum punya.
     *
     * Dipanggil saat PPK menandatangani; kodenya menetap agar QR pada
     * cetakan lama tetap dapat diperiksa.
     */
    public function terbitkanKodeVerifikasi(): void
    {
        if ($this->kode_verifikasi) {
            return;
        }

        do {
            $kode = 'NOM-'.mb_strtoupper(Str::random(10));
        } while (self::where('kode_verifikasi', $kode)->exists());

        $this->update(['kode_verifikasi' => $kode]);
    }

    public function sudahDikirim(): bool
    {
        return $this->dikirim_at !== null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->sudahDikirim() => 'Terkirim ke Tim Keuangan',
            $this->sudahDitandatangani() => 'Ditandatangani PPK',
            default => 'Menunggu Tanda Tangan PPK',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match (true) {
            $this->sudahDikirim() => 'bg-emerald-100 text-emerald-700',
            $this->sudahDitandatangani() => 'bg-teal-100 text-teal-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }
}
