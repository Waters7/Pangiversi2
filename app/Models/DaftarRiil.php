<?php

namespace App\Models;

use App\Enums\KategoriBiaya;
use App\Services\JalurPersetujuan;
use App\Services\TautanVerifikasi;
use Database\Factories\DaftarRiilFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Daftar pengeluaran riil per peserta perjalanan dinas, yaitu biaya nyata
 * yang tidak seluruhnya didukung bukti dan dinyatakan oleh pelaksana.
 * Dokumen ini ditandatangani PPK sebagai dasar pembayaran.
 */
class DaftarRiil extends Model
{
    /** @use HasFactory<DaftarRiilFactory> */
    use HasFactory;

    protected $table = 'daftar_riil';

    /** Lama masa sanggah sejak daftar dikirim ke pelaksana perjalanan. */
    public const HARI_MASA_SANGGAH = 7;

    protected $fillable = [
        'id_usulan',
        'id_peserta',
        'total_riil',
        'keterangan',
        'id_ppk',
        'ditandatangani_at',
        'kode_verifikasi',
        'diajukan_at',
        'divalidasi_at',
        'id_validator',
        'dikembalikan_at',
        'alasan_kembali',
        'id_pengembali',
        'dikirim_ke_pegawai_at',
        'batas_sanggah',
        'disetujui_pegawai_at',
        'kode_konfirmasi',
        'sanggahan',
        'disanggah_at',
        'rincian_disetujui_at',
        'rincian_disanggah_at',
        'rincian_sanggahan',
        'rincian_kode_konfirmasi',
        'rincian_ditandatangani_at',
        'rincian_id_ppk',
        'rincian_kode_verifikasi',
        'dibayar_at',
        'bukti_bayar',
        'id_pembayar',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_riil' => 'float',
            'ditandatangani_at' => 'datetime',
            'diajukan_at' => 'datetime',
            'divalidasi_at' => 'datetime',
            'dikembalikan_at' => 'datetime',
            'dikirim_ke_pegawai_at' => 'datetime',
            'batas_sanggah' => 'date',
            'disetujui_pegawai_at' => 'datetime',
            'disanggah_at' => 'datetime',
            'rincian_disetujui_at' => 'datetime',
            'rincian_disanggah_at' => 'datetime',
            'rincian_ditandatangani_at' => 'datetime',
            'dibayar_at' => 'date',
        ];
    }

    /**
     * Jalur persetujuan salah satu dokumen — 'riil' atau 'rincian'.
     */
    public function jalur(string $jenis = JalurPersetujuan::RIIL): JalurPersetujuan
    {
        return new JalurPersetujuan($this, JalurPersetujuan::kenali($jenis));
    }

    public function jalurRincian(): JalurPersetujuan
    {
        return $this->jalur(JalurPersetujuan::RINCIAN);
    }

    /**
     * Nominal rincian biaya perjalanan dinas: seluruh komponen kecuali
     * transport lokal, yang sudah pindah ke daftar pengeluaran riil.
     */
    public function totalRincianBiaya(): float
    {
        return (float) ($this->usulan?->keuangan?->rincianBiaya ?? collect())
            ->reject(fn ($baris) => $baris->kategori === KategoriBiaya::TransportLokal)
            ->sum('jumlah');
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * @return BelongsTo<PesertaUsulan, $this>
     */
    public function peserta(): BelongsTo
    {
        return $this->belongsTo(PesertaUsulan::class, 'id_peserta');
    }

    /**
     * PPK yang membubuhkan tanda tangan.
     *
     * @return BelongsTo<User, $this>
     */
    public function ppk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_ppk');
    }

    /**
     * Anggota tim keuangan yang memeriksa nominal transport lokalnya.
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_validator');
    }

    /**
     * Sudah diperiksa tim keuangan, jadi boleh berjalan ke pelaksana.
     *
     * Daftar tanpa nominal tidak perlu divalidasi: tidak ada yang diganti,
     * dan menahannya hanya akan memacetkan berkas yang memang tidak
     * mengeluarkan transport lokal.
     */
    public function sudahDivalidasi(): bool
    {
        return $this->divalidasi_at !== null || $this->total_riil <= 0;
    }

    /**
     * Anggota PPK yang mengembalikan berkas ini kepada tim keuangan.
     */
    public function pengembali(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengembali');
    }

    /**
     * Bendahara yang membayarkan penggantian transport lokalnya.
     */
    public function pembayar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pembayar');
    }

    public function sudahDibayar(): bool
    {
        return $this->dibayar_at !== null;
    }

    /**
     * Siap diganti: sudah disahkan PPK, bernominal, dan belum dibayarkan.
     */
    public function siapDiganti(): bool
    {
        return $this->sudah_ditandatangani && $this->total_riil > 0 && ! $this->sudahDibayar();
    }

    /**
     * Catat penggantian transport lokal berikut bukti transfernya.
     */
    public function bayarTransport(string $tanggal, string $bukti, User $bendahara): void
    {
        $this->update([
            'dibayar_at' => $tanggal,
            'bukti_bayar' => $bukti,
            'id_pembayar' => $bendahara->id,
        ]);
    }

    public function sedangDikembalikan(): bool
    {
        return $this->dikembalikan_at !== null && ! $this->sudah_ditandatangani;
    }

    /**
     * PPK mengembalikan berkas kepada tim keuangan untuk diperbaiki.
     *
     * Validasinya ikut dicabut: yang dikembalikan berarti belum benar, jadi
     * harus diperiksa ulang sebelum berjalan lagi ke pelaksana.
     */
    public function kembalikanKeKeuangan(string $alasan, User $ppk): void
    {
        $this->update([
            'dikembalikan_at' => now(),
            'alasan_kembali' => $alasan,
            'id_pengembali' => $ppk->id,
            'divalidasi_at' => null,
            'id_validator' => null,
            'dikirim_ke_pegawai_at' => null,
            'batas_sanggah' => null,
        ]);

        $this->jalur()->bukaUlang();
        $this->jalurRincian()->bukaUlang();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeSudahDitandatangani(Builder $query): void
    {
        $query->whereNotNull('ditandatangani_at');
    }

    public function getSudahDitandatanganiAttribute(): bool
    {
        return $this->ditandatangani_at !== null;
    }

    /**
     * Berkas yang menunggu tanda tangan PPK pada salah satu jalurnya.
     *
     * Cerminan SQL dari JalurPersetujuan::siapDitandatanganiPpk(), supaya
     * jumlah antrean dapat dihitung tanpa memuat seluruh berkas ke memori.
     * Keduanya diuji berdampingan agar tidak berselisih.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeMenungguPpk(Builder $query, string $jenis = JalurPersetujuan::RIIL): void
    {
        $kolom = JalurPersetujuan::kolom($jenis);

        $query
            ->whereNull($kolom['ditandatangani'])
            // Disetujui pelaksana, atau masa sanggahnya lewat tanpa keberatan.
            ->where(fn (Builder $q) => $q
                ->whereNotNull($kolom['disetujui'])
                ->orWhere(fn (Builder $lewat) => $lewat
                    ->whereNull($kolom['disetujui'])
                    ->whereNull($kolom['disanggah'])
                    ->whereNotNull('dikirim_ke_pegawai_at')
                    ->whereNotNull('batas_sanggah')
                    ->whereDate('batas_sanggah', '<', today())));

        // Nilai dokumennya harus ada isinya: daftar riil membawa totalnya
        // sendiri, rincian biaya menjumlah barisnya di luar transport lokal.
        if (JalurPersetujuan::kenali($jenis) === JalurPersetujuan::RINCIAN) {
            $query->whereHas(
                'usulan.keuangan.rincianBiaya',
                fn (Builder $q) => $q
                    ->where('kategori', '!=', KategoriBiaya::TransportLokal->value)
                    ->where('jumlah', '>', 0),
            );

            return;
        }

        $query->where('total_riil', '>', 0);
    }

    // ── Persetujuan pelaksana perjalanan ──

    public function sudahDikirimKePegawai(): bool
    {
        return $this->dikirim_ke_pegawai_at !== null;
    }

    public function sudahDisetujuiPegawai(): bool
    {
        return $this->disetujui_pegawai_at !== null;
    }

    public function sedangDisanggah(): bool
    {
        return $this->disanggah_at !== null && ! $this->sudahDisetujuiPegawai();
    }

    /**
     * Masa sanggah masih berjalan, sehingga pelaksana boleh menyatakan sikap.
     */
    public function masaSanggahBerjalan(): bool
    {
        return $this->sudahDikirimKePegawai()
            && ! $this->sudahDisetujuiPegawai()
            && $this->batas_sanggah !== null
            && ! today()->greaterThan($this->batas_sanggah);
    }

    /**
     * Masa sanggah habis tanpa tanggapan — nominal dianggap diterima.
     */
    public function sanggahKedaluwarsa(): bool
    {
        return $this->sudahDikirimKePegawai()
            && ! $this->sudahDisetujuiPegawai()
            && ! $this->sedangDisanggah()
            && $this->batas_sanggah !== null
            && today()->greaterThan($this->batas_sanggah);
    }

    /**
     * PPK hanya membubuhkan tanda tangan setelah pelaksana menyetujui, atau
     * setelah masa sanggah lewat tanpa keberatan.
     */
    public function siapDitandatanganiPpk(): bool
    {
        return $this->total_riil > 0
            && ! $this->sedangDisanggah()
            && ($this->sudahDisetujuiPegawai() || $this->sanggahKedaluwarsa());
    }

    public function sisaHariSanggah(): int
    {
        if (! $this->masaSanggahBerjalan()) {
            return 0;
        }

        return (int) today()->diffInDays($this->batas_sanggah, absolute: true);
    }

    public function kirimKePegawai(): void
    {
        $this->update([
            'dikirim_ke_pegawai_at' => now(),
            'batas_sanggah' => today()->addDays(self::HARI_MASA_SANGGAH),
            // Pengembalian sebelumnya dianggap sudah ditindaklanjuti.
            'dikembalikan_at' => null,
        ]);

        // Kedua dokumen dikirim sepaket, jadi keduanya dibuka kembali.
        $this->jalur()->bukaUlang();
        $this->jalurRincian()->bukaUlang();
    }

    /**
     * Pelaksana menyatakan nominalnya sudah sesuai.
     *
     * Persetujuan ini ikut diberi kode konfirmasi agar tanda tangannya pada
     * daftar pengeluaran riil punya legalitas yang dapat ditelusuri, sama
     * seperti tanda tangan PPK.
     */
    public function setujuiPegawai(): void
    {
        $this->jalur()->setujui();
    }

    public function sanggah(string $alasan): void
    {
        $this->jalur()->sanggah($alasan);
    }

    // ── Tampilan status ──

    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->sudah_ditandatangani => 'Ditandatangani PPK',
            $this->sedangDikembalikan() => 'Dikembalikan ke Tim Keuangan',
            $this->sedangDisanggah() => 'Disanggah Pelaksana',
            $this->sudahDisetujuiPegawai() => 'Disetujui Pelaksana',
            $this->sanggahKedaluwarsa() => 'Masa Sanggah Berakhir',
            $this->masaSanggahBerjalan() => 'Menunggu Tanggapan Pelaksana',
            // Sisanya: sudah bernominal tetapi belum dikirim ke pelaksana,
            // jadi masih menunggu pemeriksaan tim keuangan — bukan PPK.
            $this->total_riil > 0 => 'Menunggu Verifikasi Tim Keuangan',
            default => 'Nominal Belum Diisi',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match (true) {
            $this->sudah_ditandatangani => 'bg-emerald-100 text-emerald-700',
            $this->sedangDikembalikan() => 'bg-orange-100 text-orange-700',
            $this->sedangDisanggah() => 'bg-red-100 text-red-700',
            $this->sudahDisetujuiPegawai(), $this->sanggahKedaluwarsa() => 'bg-teal-100 text-teal-700',
            $this->masaSanggahBerjalan() => 'bg-amber-100 text-amber-700',
            $this->total_riil > 0 => 'bg-blue-100 text-blue-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * Bubuhkan tanda tangan PPK beserta kode verifikasi publiknya.
     *
     * Kode dibuat sekali dan dipertahankan selama dokumen masih sah, sehingga
     * QR yang sudah tercetak tetap dapat ditelusuri.
     */
    public function tandaTangani(User $ppk): void
    {
        $this->jalur()->tandaTangani($ppk);
    }

    /**
     * Cabut tanda tangan agar daftar dapat dikoreksi kembali.
     *
     * Kode verifikasi ikut dicabut supaya dokumen lama yang sudah beredar
     * tidak lagi tervalidasi.
     */
    public function batalkanTandaTangan(): void
    {
        $this->jalur()->batalkanTandaTangan();
    }

    /**
     * Alamat halaman verifikasi publik yang ditanam di dalam QR tanda
     * tangan PPK.
     */
    public function urlVerifikasi(): ?string
    {
        return app(TautanVerifikasi::class)->untuk($this->kode_verifikasi);
    }

    /**
     * Alamat halaman verifikasi untuk QR tanda tangan pelaksana.
     */
    public function urlKonfirmasi(): ?string
    {
        return app(TautanVerifikasi::class)->untuk($this->kode_konfirmasi);
    }

    /**
     * Kode acak yang mudah dibaca ulang bila QR gagal dipindai.
     */
    public static function buatKodeVerifikasi(): string
    {
        return self::buatKodeUnik('PPK-', 'kode_verifikasi');
    }

    public static function buatKodeKonfirmasi(): string
    {
        return self::buatKodeUnik('PLK-', 'kode_konfirmasi');
    }

    private static function buatKodeUnik(string $awalan, string $kolom): string
    {
        do {
            $kode = $awalan.mb_strtoupper(Str::random(10));
        } while (self::where($kolom, $kode)->exists());

        return $kode;
    }

    /**
     * Baris rincian yang tercetak pada Daftar Pengeluaran Riil.
     *
     * @return HasMany<RincianDaftarRiil, $this>
     */
    public function rincian()
    {
        return $this->hasMany(RincianDaftarRiil::class, 'id_daftar_riil')->orderBy('urutan');
    }

    /**
     * Hitung ulang total dari baris rinciannya.
     *
     * Totalnya tidak diketik lepas: ia jumlah barisnya, supaya angka pada
     * dokumen tidak pernah berbeda dari angka yang dirinci.
     *
     * Daftar yang belum punya satu pun baris dibiarkan apa adanya. Tanpa
     * pengecualian ini, daftar lama — yang totalnya dulu diketik langsung
     * sebelum ada tabel rincian — akan dinolkan pada penyelarasan pertama.
     */
    /**
     * @param  bool  $paksa  Hitung ulang meski barisnya kosong — dipakai
     *                       penyelarasan dokumen, yang memang berwenang
     *                       mengosongkan totalnya saat seluruh nota dicabut.
     */
    public function hitungTotal(bool $paksa = false): void
    {
        // Daftar warisan menyimpan total tanpa baris rincian. Tanpa penjagaan
        // ini, membukanya saja sudah menolkan nominalnya.
        if (! $paksa && ! $this->rincian()->exists()) {
            return;
        }

        $this->update(['total_riil' => (float) $this->rincian()->sum('nominal')]);
    }
}
