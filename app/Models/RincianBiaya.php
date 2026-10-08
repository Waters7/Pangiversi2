<?php

namespace App\Models;

use App\Enums\CaraBayarBiaya;
use App\Enums\IsianBiaya;
use App\Enums\KategoriBiaya;
use Database\Factories\RincianBiayaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu komponen rincian biaya perjalanan dinas.
 *
 * Penghapusan bersifat lunak hanya untuk baris dari berkas pelaksana: baris
 * itu ditandai terhapus supaya tidak tersalin lagi dari berkasnya, dan dapat
 * dikembalikan. Baris tulisan tim keuangan dibuang sungguhan.
 */
class RincianBiaya extends Model
{
    /** @use HasFactory<RincianBiayaFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kategori',
        'komponen',
        'volume',
        'satuan',
        'harga_satuan',
        'jumlah',
        'keterangan',
        'sumber',
        'kunci_sumber',
        'isi_berkas',
        'isian_pelaksana',
        'divalidasi_at',
        'id_validator',
        'cara_bayar',
        'cara_bayar_dikonfirmasi_at',
        'id_pengonfirmasi_bayar',
        'id_keuangan',
    ];

    /** Baris yang diketik langsung tim keuangan. */
    public const SUMBER_KEUANGAN = 'keuangan';

    /** Baris yang lahir dari nominal pada berkas pertanggungjawaban. */
    public const SUMBER_DOKUMEN = 'dokumen';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kategori' => KategoriBiaya::class,
            'isian_pelaksana' => IsianBiaya::class,
            'isi_berkas' => 'array',
            'harga_satuan' => 'float',
            'jumlah' => 'float',
            'divalidasi_at' => 'datetime',
            'cara_bayar' => CaraBayarBiaya::class,
            'cara_bayar_dikonfirmasi_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Komponen yang baru tercatat setelah uang muka ditransfer jelas tidak
        // ikut di dalam transfer itu: ia dibayarkan saat pelunasan.
        static::creating(function (self $baris): void {
            if ($baris->cara_bayar === null
                && $baris->kategori !== KategoriBiaya::UangHarian
                && Keuangan::whereKey($baris->id_keuangan)->whereNotNull('tanggal_transfer')->exists()) {
                $baris->cara_bayar = CaraBayarBiaya::Penggantian;
            }
        });
    }

    /**
     * Berasal dari nominal yang diketik pelaksana, bukan dari tim keuangan.
     */
    public function dariDokumen(): bool
    {
        return $this->sumber === self::SUMBER_DOKUMEN;
    }

    /**
     * Isian berkas pelaksana yang nominalnya ditetapkan lewat baris ini.
     *
     * Hanya baris tulisan tim keuangan yang mewakili isian pelaksana; baris
     * yang lahir dari berkas pelaksana adalah isian itu sendiri.
     *
     * @return list<IsianBiaya>
     */
    public function isianYangDiwakili(): array
    {
        return $this->dariDokumen() ? [] : ($this->isian_pelaksana?->mencakup() ?? []);
    }

    /**
     * Sudah diperiksa tim keuangan.
     *
     * Baris yang diketik tim keuangan sendiri terhitung sah sejak dibuat;
     * yang perlu diperiksa hanyalah angka yang datang dari pelaksana.
     */
    public function sudahDivalidasi(): bool
    {
        return ! $this->dariDokumen() || $this->divalidasi_at !== null;
    }

    /**
     * Ikut dihitung pada total, uang muka, sisa bayar, dan cetak rincian.
     *
     * Hanya angka yang ditulis tim keuangan dan angka pelaksana yang sudah
     * divalidasi: nominal yang belum diperiksa tampil pada tabel, tetapi
     * belum menjadi angka yang dibayarkan. Transport lokal dihitung lewat
     * Daftar Pengeluaran Riil.
     */
    public function terhitung(): bool
    {
        return $this->sudahDivalidasi() && $this->kategori !== KategoriBiaya::TransportLokal;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeTerhitung(Builder $query): void
    {
        $query->where('kategori', '!=', KategoriBiaya::TransportLokal->value)
            ->where(fn (Builder $q) => $q
                ->where('sumber', '!=', self::SUMBER_DOKUMEN)
                ->orWhereNotNull('divalidasi_at'));
    }

    /**
     * Cara komponen ini dibayarkan; null untuk uang harian, yang selalu
     * dibagi 80% di muka dan 20% saat pelunasan. Yang belum ditentukan
     * dihitung masuk uang muka.
     */
    public function caraBayar(): ?CaraBayarBiaya
    {
        if ($this->kategori === KategoriBiaya::UangHarian) {
            return null;
        }

        return $this->cara_bayar ?? CaraBayarBiaya::UangMuka;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengonfirmasiBayar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengonfirmasi_bayar');
    }

    /**
     * @return BelongsTo<Keuangan, $this>
     */
    public function keuangan(): BelongsTo
    {
        return $this->belongsTo(Keuangan::class, 'id_keuangan');
    }

    /**
     * Baris perkalian "volume satuan × tarif" ikut tercetak di bawah komponen.
     *
     * Uang harian selalu dirinci, termasuk yang hanya sehari: pemeriksa
     * menakar tarifnya terhadap SBM, jadi "1 OH × Rp 380.000" tetap perlu
     * terbaca. Komponen lain cukup dirinci bila volumenya lebih dari satu.
     */
    public function tampilkanPerkalian(): bool
    {
        return $this->volume > 1 || ($this->kategori === KategoriBiaya::UangHarian && $this->volume > 0);
    }

    /**
     * Uraian sebagaimana tercetak pada dokumen rincian resmi,
     * misalnya "2 Hari x Rp 370.000".
     */
    public function getUraianAttribute(): string
    {
        if ($this->kategori === KategoriBiaya::UangHarian && $this->volume > 0) {
            return "{$this->volume} {$this->satuan} x Rp ".number_format($this->harga_satuan, 0, ',', '.');
        }

        return $this->keterangan ?: $this->komponen;
    }
}
