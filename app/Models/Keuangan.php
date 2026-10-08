<?php

namespace App\Models;

use App\Enums\CaraBayarBiaya;
use App\Enums\KategoriBiaya;
use App\Services\TautanVerifikasi;
use Database\Factories\KeuanganFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Keuangan extends Model
{
    /** @use HasFactory<KeuanganFactory> */
    use HasFactory;

    protected $table = 'keuangan';

    public const STATUS_BELUM = 'belum bayar';

    public const STATUS_SEBAGIAN = 'bayar sebagian';

    public const STATUS_LUNAS = 'lunas';

    /** Bagian uang harian yang dibayarkan di muka. */
    public const PORSI_UANG_HARIAN_DI_MUKA = 0.8;

    protected $fillable = [
        'tanggal_transfer',
        'tanggal_pelunasan',
        'total',
        'uang_muka',
        'sisa',
        'status',
        'kode_konfirmasi_bayar',
        'dikonfirmasi_bayar_at',
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
            'dikonfirmasi_bayar_at' => 'datetime',
            'total' => 'float',
            'uang_muka' => 'float',
            'sisa' => 'float',
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
     * Jurnal pembayaran — tiap pembayaran dan pembatalannya, berikut pencatat
     * dan bukti transfernya.
     *
     * @return HasMany<RiwayatPembayaran, $this>
     */
    public function riwayatPembayaran(): HasMany
    {
        return $this->hasMany(RiwayatPembayaran::class, 'id_keuangan')->orderBy('id');
    }

    /**
     * Bendahara yang tercatat mengonfirmasi pembayaran.
     *
     * Satuan kerja hanya punya satu bendahara pengeluaran, jadi identitasnya
     * diambil dari peran, bukan disimpan ulang per transaksi.
     */
    public function getPembayarBendaharaAttribute(): ?User
    {
        return User::where('role', User::ROLE_BENDAHARA)->orderBy('id')->first();
    }

    /**
     * Hitung ulang total, uang muka, dan sisa dari rincian biaya.
     *
     * Yang dihitung hanya baris sah — tulisan tim keuangan dan nominal
     * pelaksana yang sudah divalidasi. Uang harian dibagi 80/20; komponen
     * lain masuk uang muka seutuhnya, kecuali yang dibayar pelaksana lebih
     * dahulu: itu diganti saat pelunasan, jadi masuk sisa bayar.
     *
     * Uang muka yang sudah ditransfer tidak bergeser lagi — uangnya sudah
     * keluar. Perubahan sesudahnya, baris baru maupun koreksi, seluruhnya
     * jatuh pada sisa bayar.
     */
    public function hitungTotal(): void
    {
        $rincian = $this->rincianBiaya()->terhitung()->get();
        $total = (float) $rincian->sum('jumlah');

        if ($this->uangMukaTerbayar()) {
            $this->update([
                'total' => $total,
                'sisa' => $total - (float) $this->uang_muka,
            ]);

            return;
        }

        $uangHarian = (float) $rincian
            ->filter(fn (RincianBiaya $baris) => $baris->kategori === KategoriBiaya::UangHarian)
            ->sum('jumlah');

        $penggantian = (float) $rincian
            ->filter(fn (RincianBiaya $baris) => $baris->caraBayar() === CaraBayarBiaya::Penggantian)
            ->sum('jumlah');

        $sisa = $uangHarian * (1 - self::PORSI_UANG_HARIAN_DI_MUKA) + $penggantian;

        $this->update([
            'total' => $total,
            'uang_muka' => $total - $sisa,
            'sisa' => $sisa,
        ]);
    }

    /**
     * Saat uang muka ditransfer, catat komponen mana yang ikut di dalamnya.
     *
     * Komponen sah yang cara bayarnya belum ditentukan memang terhitung
     * masuk uang muka; nominal pelaksana yang belum divalidasi tidak ikut,
     * jadi kelak diganti saat pelunasan.
     */
    public function catatCaraBayarUangMuka(): void
    {
        $this->rincianBiaya()
            ->whereNull('cara_bayar')
            ->where('kategori', '!=', KategoriBiaya::UangHarian->value)
            ->get()
            ->each(fn (RincianBiaya $baris) => $baris->update([
                'cara_bayar' => $baris->terhitung() ? CaraBayarBiaya::UangMuka : CaraBayarBiaya::Penggantian,
            ]));
    }

    /**
     * Uang harian sah pada rincian biaya — dasar pembagian 80/20 di atas.
     */
    public function uangHarian(): float
    {
        return (float) $this->rincianBiaya()
            ->terhitung()
            ->where('kategori', KategoriBiaya::UangHarian->value)
            ->sum('jumlah');
    }

    /**
     * Reimbursement transport lokal, diambil dari daftar pengeluaran riil.
     *
     * Tidak termasuk dalam `total` karena transport lokal memang di luar
     * rincian biaya: ia dibayarkan sebagai penggantian, bukan sebagai bagian
     * dari uang muka.
     *
     * Yang sudah ditransfer sendiri lewat menu Pembayaran tidak ikut lagi
     * di sini — kalau tidak, satu penggantian terbayar dua kali.
     */
    public function reimbursementTransport(): float
    {
        return (float) ($this->usulan?->daftarRiil ?? collect())
            ->where('sudah_ditandatangani', true)
            ->filter(fn ($daftar) => ! $daftar->sudahDibayar())
            ->sum('total_riil');
    }

    /**
     * Penggantian transport lokal yang sudah ditransfer terpisah.
     */
    public function transportSudahDiganti(): float
    {
        return (float) ($this->usulan?->daftarRiil ?? collect())
            ->filter(fn ($daftar) => $daftar->sudahDibayar())
            ->sum('total_riil');
    }

    /**
     * Nilai yang dibayarkan pada pelunasan: sisa uang harian ditambah
     * penggantian transport lokal.
     */
    public function nilaiPelunasan(): float
    {
        return (float) $this->sisa + $this->reimbursementTransport();
    }

    // ── Status pembayaran ──

    public function sudahLunas(): bool
    {
        return $this->status === self::STATUS_LUNAS;
    }

    public function uangMukaTerbayar(): bool
    {
        return $this->tanggal_transfer !== null;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeLunas(Builder $query): void
    {
        $query->where('status', self::STATUS_LUNAS);
    }

    // ── Konfirmasi pembayaran oleh bendahara ──

    /**
     * Terbitkan kode konfirmasi pembayaran saat pelunasan.
     *
     * Kode dipertahankan selama status masih lunas, sehingga QR yang sudah
     * tercetak pada rincian biaya tetap dapat ditelusuri.
     */
    public function konfirmasiPelunasan(): void
    {
        $this->update([
            'kode_konfirmasi_bayar' => $this->kode_konfirmasi_bayar ?? self::buatKodeKonfirmasiBayar(),
            'dikonfirmasi_bayar_at' => $this->dikonfirmasi_bayar_at ?? now(),
        ]);
    }

    /**
     * Cabut konfirmasi pembayaran agar dokumen lama tidak lagi tervalidasi.
     */
    public function batalkanKonfirmasiPelunasan(): void
    {
        $this->update([
            'kode_konfirmasi_bayar' => null,
            'dikonfirmasi_bayar_at' => null,
        ]);
    }

    public function sudahDikonfirmasiBayar(): bool
    {
        return $this->kode_konfirmasi_bayar !== null && $this->sudahLunas();
    }

    /**
     * Alamat halaman verifikasi publik yang ditanam pada QR bendahara.
     */
    public function urlKonfirmasiBayar(): ?string
    {
        return $this->sudahDikonfirmasiBayar()
            ? app(TautanVerifikasi::class)->untuk($this->kode_konfirmasi_bayar)
            : null;
    }

    public static function buatKodeKonfirmasiBayar(): string
    {
        do {
            $kode = 'BND-'.mb_strtoupper(Str::random(10));
        } while (self::where('kode_konfirmasi_bayar', $kode)->exists());

        return $kode;
    }

    // ── Tampilan ──

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_LUNAS => 'Lunas 100%',
            self::STATUS_SEBAGIAN => 'Uang Muka Terbayar',
            default => 'Belum Bayar',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_LUNAS => 'bg-emerald-100 text-emerald-700',
            self::STATUS_SEBAGIAN => 'bg-blue-100 text-blue-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }
}
