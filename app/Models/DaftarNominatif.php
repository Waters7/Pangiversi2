<?php

namespace App\Models;

use App\Services\TautanVerifikasi;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * Surat tugas yang daftarnya belum terbit dapat dibebani lebih dulu lewat
 * menu Pembebanan Anggaran. Barisnya bertanda rencana dan tersembunyi dari
 * seluruh kueri lain, sebab belum ada daftar yang dapat ditandatangani;
 * begitu terbit, baris yang sama dipakai sehingga pembebanannya terbawa.
 */
class DaftarNominatif extends Model
{
    protected $table = 'daftar_nominatif';

    protected $fillable = [
        'no_tugas',
        'rencana',
        'tanggal_tugas',
        'id_kategori_pembiayaan',
        'id_akun_pembiayaan',
        'id_tim_kerja',
        'id_ppk',
        'ditandatangani_at',
        'dikirim_at',
        'kode_verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'rencana' => 'boolean',
            'tanggal_tugas' => 'date',
            'ditandatangani_at' => 'datetime',
            'dikirim_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('terbit', fn (Builder $query) => $query->where('rencana', false));
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeTermasukRencana(Builder $query): void
    {
        $query->withoutGlobalScope('terbit');
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

    /** Tim kerja yang pagunya dibebani daftar ini, pada akun yang ditetapkan. */
    public function timKerja(): BelongsTo
    {
        return $this->belongsTo(TimKerja::class, 'id_tim_kerja');
    }

    /** Tahun yang membiayai daftar ini: tahun surat tugasnya, atau tahun daftar itu terbit. */
    public function tahunPembebanan(): ?int
    {
        return ($this->tanggal_tugas ?? $this->dikirim_at ?? $this->created_at)?->year;
    }

    /**
     * Tahun anggaran yang membiayai daftar ini, atau tahun anggaran aktif
     * bila tahunnya belum tercatat sebagai tahun anggaran.
     */
    public function tahunAnggaran(): ?TahunAnggaran
    {
        $tahun = $this->tahunPembebanan();

        return ($tahun ? TahunAnggaran::firstWhere('tahun', $tahun) : null) ?? TahunAnggaran::aktif();
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
     * Dipanggil saat PPK menandatangani; kodenya menetap selama tanda
     * tangannya berlaku agar QR pada cetakan tetap dapat diperiksa.
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

    /**
     * Cabut tanda tangan PPK supaya daftarnya dapat dikoreksi. Kodenya ikut
     * gugur, sehingga cetakan lama tidak lagi lolos verifikasi; penandatanganan
     * ulang menerbitkan kode baru.
     */
    public function batalkanTandaTangan(): void
    {
        $this->update([
            'id_ppk' => null,
            'ditandatangani_at' => null,
            'dikirim_at' => null,
            'kode_verifikasi' => null,
        ]);
    }

    public function sudahDikirim(): bool
    {
        return $this->dikirim_at !== null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->rencana => 'Nominatif Belum Terbit',
            $this->sudahDikirim() => 'Terkirim ke Tim Keuangan',
            $this->sudahDitandatangani() => 'Ditandatangani PPK',
            default => 'Menunggu Tanda Tangan PPK',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match (true) {
            (bool) $this->rencana => 'bg-slate-100 text-slate-600',
            $this->sudahDikirim() => 'bg-emerald-100 text-emerald-700',
            $this->sudahDitandatangani() => 'bg-teal-100 text-teal-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }
}
