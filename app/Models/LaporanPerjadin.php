<?php

namespace App\Models;

use App\Enums\StatusLaporanPerjadin;
use App\Services\FormatLaporanPerjadin;
use App\Services\TautanVerifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Laporan perjalanan dinas yang disusun langsung di aplikasi.
 *
 * Setelah selesai disusun, laporan dikirim pelaksana kepada pimpinan.
 * Pimpinan mengonfirmasi dan menandatanganinya, atau mengembalikannya
 * dengan catatan bila ada yang perlu direvisi. Kedua pihak meninggalkan
 * kode konfirmasi yang tercetak sebagai QR pada dokumennya.
 */
class LaporanPerjadin extends Model
{
    protected $table = 'laporan_perjadin';

    protected $fillable = [
        'id_usulan',
        'dasar',
        'hasil',
        'id_status_hasil',
        'kesimpulan',
        'diselesaikan_at',
        'dikirim_at',
        'kode_pelaksana',
        'dikonfirmasi_at',
        'id_pimpinan',
        'kode_pimpinan',
        'dikembalikan_at',
        'catatan_pimpinan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diselesaikan_at' => 'datetime',
            'dikirim_at' => 'datetime',
            'dikonfirmasi_at' => 'datetime',
            'dikembalikan_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pimpinan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pimpinan');
    }

    /**
     * @return HasMany<LaporanKegiatan, $this>
     */
    public function kegiatan(): HasMany
    {
        return $this->hasMany(LaporanKegiatan::class, 'id_laporan')->orderBy('urutan');
    }

    /**
     * @return BelongsTo<StatusHasil, $this>
     */
    public function statusHasil(): BelongsTo
    {
        return $this->belongsTo(StatusHasil::class, 'id_status_hasil');
    }

    /**
     * @return HasMany<TindakLanjut, $this>
     */
    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(TindakLanjut::class, 'id_laporan')->orderBy('urutan');
    }

    /**
     * Laporan layak diselesaikan bila sudah memuat sedikitnya satu uraian
     * kegiatan, satu rencana tindak lanjut, dan status hasilnya dipilih.
     */
    public function layakDiselesaikan(): bool
    {
        return $this->kegiatan()->exists()
            && $this->tindakLanjut()->exists()
            && $this->id_status_hasil !== null;
    }

    /**
     * Dasar pelaksanaan, diturunkan dari surat tugas dan maksud perjalanan
     * yang sudah tercatat pada usulannya.
     *
     * Tidak lagi diminta ke pelaksana: keduanya sudah diisi di awal, dan
     * mengetiknya ulang hanya membuka peluang berbeda dari yang tercatat.
     */
    public function dasarPelaksanaan(): string
    {
        $usulan = $this->usulan;

        if (! $usulan) {
            return (string) $this->dasar;
        }

        $bagian = array_filter([
            filled($usulan->no_tugas) ? 'Surat Tugas Nomor '.$usulan->no_tugas : null,
            app(FormatLaporanPerjadin::class)->maksud($usulan),
        ]);

        return implode(' — ', $bagian);
    }

    /**
     * Laporan masih boleh disunting.
     *
     * Terkunci sejak dikirim ke pimpinan — isinya sedang diperiksa — dan
     * tetap terkunci setelah dikonfirmasi, karena sejak itu dokumennya
     * sudah ditandatangani dan menjadi dasar pelunasan. Perjalanan yang
     * sudah ditutup juga tidak dibuka lagi.
     */
    public function bolehDisunting(): bool
    {
        return ! $this->sudahDikirim()
            && ! $this->sudahDikonfirmasi()
            && $this->usulan?->status !== 'selesai';
    }

    public function sudahSelesai(): bool
    {
        return $this->diselesaikan_at !== null;
    }

    /** Sudah di meja pimpinan dan belum diputuskan. */
    public function sudahDikirim(): bool
    {
        return $this->dikirim_at !== null && ! $this->sudahDikonfirmasi();
    }

    public function sudahDikonfirmasi(): bool
    {
        return $this->dikonfirmasi_at !== null;
    }

    /** Dikembalikan pimpinan dan belum diselesaikan ulang oleh pelaksana. */
    public function perluRevisi(): bool
    {
        return $this->dikembalikan_at !== null && ! $this->sudahSelesai();
    }

    public function status(): StatusLaporanPerjadin
    {
        return match (true) {
            $this->sudahDikonfirmasi() => StatusLaporanPerjadin::Dikonfirmasi,
            $this->sudahDikirim() => StatusLaporanPerjadin::MenungguKonfirmasi,
            $this->perluRevisi() => StatusLaporanPerjadin::PerluRevisi,
            $this->sudahSelesai() => StatusLaporanPerjadin::Selesai,
            default => StatusLaporanPerjadin::Draf,
        };
    }

    // ── Peralihan tahap ──

    /**
     * Kirim laporan yang sudah selesai kepada pimpinan.
     *
     * Kode pelaksana terbit di sini dan dipertahankan sampai laporan
     * dikembalikan atau ditarik, sehingga QR yang sudah tercetak tetap
     * dapat ditelusuri selama laporannya masih berlaku.
     */
    public function kirim(): void
    {
        $this->update([
            'dikirim_at' => now(),
            'kode_pelaksana' => self::buatKodeUnik('LPK-', 'kode_pelaksana'),
            'dikembalikan_at' => null,
        ]);
    }

    /**
     * Tarik kembali laporan yang belum diputuskan pimpinan agar dapat
     * diperbaiki. Kodenya dicabut supaya dokumen yang sudah beredar tidak
     * lagi tervalidasi.
     */
    public function tarik(): void
    {
        $this->update([
            'dikirim_at' => null,
            'kode_pelaksana' => null,
            'diselesaikan_at' => null,
        ]);
    }

    /**
     * Bubuhkan tanda tangan pimpinan beserta kode konfirmasinya.
     */
    public function konfirmasi(User $pimpinan, ?string $catatan = null): void
    {
        $this->update([
            'dikonfirmasi_at' => now(),
            'id_pimpinan' => $pimpinan->id,
            'kode_pimpinan' => self::buatKodeUnik('PIM-', 'kode_pimpinan'),
            'catatan_pimpinan' => $catatan,
        ]);
    }

    /**
     * Cabut konfirmasi pimpinan agar laporan kembali menunggu keputusan.
     */
    public function batalkanKonfirmasi(): void
    {
        $this->update([
            'dikonfirmasi_at' => null,
            'id_pimpinan' => null,
            'kode_pimpinan' => null,
        ]);
    }

    /**
     * Kembalikan kepada pelaksana untuk direvisi. Laporan dibuka lagi,
     * kode pelaksananya dicabut, dan catatan pimpinan menjadi arahan
     * perbaikannya.
     */
    public function kembalikan(User $pimpinan, string $catatan): void
    {
        $this->update([
            'dikirim_at' => null,
            'kode_pelaksana' => null,
            'diselesaikan_at' => null,
            'dikembalikan_at' => now(),
            'id_pimpinan' => $pimpinan->id,
            'catatan_pimpinan' => $catatan,
        ]);
    }

    // ── Verifikasi QR ──

    /**
     * Alamat halaman verifikasi untuk QR tanda tangan pelaksana.
     */
    public function urlKonfirmasiPelaksana(): ?string
    {
        return app(TautanVerifikasi::class)->untuk($this->kode_pelaksana);
    }

    /**
     * Alamat halaman verifikasi untuk QR tanda tangan pimpinan.
     */
    public function urlKonfirmasiPimpinan(): ?string
    {
        return app(TautanVerifikasi::class)->untuk($this->kode_pimpinan);
    }

    /**
     * Kode acak yang mudah dibaca ulang bila QR gagal dipindai.
     */
    private static function buatKodeUnik(string $awalan, string $kolom): string
    {
        do {
            $kode = $awalan.mb_strtoupper(Str::random(10));
        } while (self::where($kolom, $kode)->exists());

        return $kode;
    }

    // ── Cakupan ──

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeMenungguKonfirmasi(Builder $query): void
    {
        $query->whereNotNull('dikirim_at')->whereNull('dikonfirmasi_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeDikonfirmasi(Builder $query): void
    {
        $query->whereNotNull('dikonfirmasi_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopePerluRevisi(Builder $query): void
    {
        $query->whereNotNull('dikembalikan_at')->whereNull('diselesaikan_at');
    }
}
