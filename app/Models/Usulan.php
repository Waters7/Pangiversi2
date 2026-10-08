<?php

namespace App\Models;

use App\Enums\JenisPerjadin;
use App\Enums\LevelPersetujuan;
use App\Enums\StatusUsulan;
use App\Services\PenagihDokumen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Usulan extends Model
{
    use HasFactory;

    protected $table = 'usulan';

    public function getRouteKeyName(): string
    {
        return 'no_usulan';
    }

    public const PENGAJUAN_PERSONAL = 'personal';

    public const PENGAJUAN_KELOMPOK = 'kelompok';

    public const KONFIRMASI_MENUNGGU = 'menunggu';

    public const KONFIRMASI_DIKONFIRMASI = 'dikonfirmasi';

    public const KONFIRMASI_DIBATALKAN = 'dibatalkan';

    /**
     * Berkas pertanggungjawaban yang wajib lengkap sebelum usulan ditutup.
     *
     * Nota transportasi termasuk wajib: tanpa bukti ini, biaya transportasi
     * selama perjalanan tidak dapat diganti oleh tim keuangan.
     *
     * @var list<string>
     */
    /**
     * Berkas unggahan yang wajib ada pada pertanggungjawaban.
     *
     * Boarding pass kini melekat pada tiap tiket, nota transportasi pada
     * tiap ruas perjalanan, dan laporan hasil disusun di aplikasi — jadi
     * ketiganya tidak lagi berupa satu kolom unggahan di sini.
     * Kelengkapan seutuhnya diperiksa PenagihDokumen::berkasKurang().
     *
     * @var list<string>
     */
    /**
     * Berkas yang wajib diunggah pelaksana.
     *
     * Surat tugas tidak termasuk: nomornya terkunci dari SPD, tidak
     * diunggah ulang. Faktur juga tidak — satuan kerja tidak
     * menerbitkannya.
     */
    public const DOKUMEN_LPJ_WAJIB = [
        'sppd',
        'kwintasi',
        'bill_hotel',
    ];

    protected $fillable = [
        'no_usulan',
        'no_tugas',
        'status',
        'jenis_pengajuan',
        'jenis_perjadin',
        'lokasi',
        'instansi',
        'tanggal_mulai',
        'tanggal_selesai',
        'uraian',
        'catatan',
        'id_user',
        'id_pembuat',
        'id_kegiatan',
        'id_kategori_perjadin',
        'id_spd',
        'no_spd',
        'id_tahun_anggaran',
        'id_lokasi',
        'kode_rombongan',
        'konfirmasi',
        'alasan_batal',
        'dikonfirmasi_at',
        'pengingat_terakhir_at',
        'pengingat_terkirim',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dikonfirmasi_at' => 'datetime',
            'pengingat_terakhir_at' => 'datetime',
            'pengingat_terkirim' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function kegiatan()
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan');
    }

    /**
     * Kategori perjalanan dinas — menentukan kelas biayanya.
     *
     * @return BelongsTo<KategoriPerjadin, $this>
     */
    /**
     * Perjalanan dalam kota menuntut berkas yang jauh lebih sedikit.
     *
     * Usulan tanpa kategori dianggap luar kota: lebih aman menagih berkas
     * yang ternyata tidak perlu daripada melewatkan yang wajib.
     */
    public function dalamKota(): bool
    {
        return $this->kategoriPerjadin?->dalamKota() === true;
    }

    /**
     * Jalur pengajuan usulan ini — dalam kota, luar kota, atau supervisi.
     *
     * Usulan lama belum menyimpannya, jadi disimpulkan dari kategorinya
     * supaya penyuntingan tetap memakai aturan yang sama.
     */
    public function jalur(): JenisPerjadin
    {
        return JenisPerjadin::dari($this->jenis_perjadin)
            ?? JenisPerjadin::untukKategori($this->kategoriPerjadin);
    }

    /** Supervisi di dalam kota tidak berdasar SPD bertanda tangan. */
    public function butuhSpd(): bool
    {
        return $this->jalur()->butuhSpd($this->kategoriPerjadin);
    }

    public function kategoriPerjadin(): BelongsTo
    {
        return $this->belongsTo(KategoriPerjadin::class, 'id_kategori_perjadin');
    }

    public function dokumen()
    {
        return $this->hasMany(Dokumen::class, 'id_usulan');
    }

    /**
     * Berkas surat tugas pelaksana: yang diunggah atau disalin saat
     * pengajuan, atau — untuk usulan lama — yang melekat pada SPD-nya.
     */
    public function berkasSuratTugas(): ?string
    {
        $dariUsulan = $this->dokumen->sortByDesc('id')->first(fn (Dokumen $dokumen) => filled($dokumen->surat_tugas))?->surat_tugas;

        return $dariUsulan ?? ($this->spd?->punyaSuratTugas() ? $this->spd->surat_tugas : null);
    }

    public function keuangan()
    {
        return $this->hasOne(Keuangan::class, 'id_usulan');
    }

    /**
     * Surat Perjalanan Dinas yang menjadi dasar penugasan usulan ini.
     */
    public function spd()
    {
        return $this->belongsTo(SuratPerjalananDinas::class, 'id_spd');
    }

    /**
     * Tiket pergi dan pulang pada berkas pertanggungjawaban.
     */
    public function tiket()
    {
        return $this->hasMany(TiketPerjadin::class, 'id_usulan');
    }

    /**
     * Empat ruas nota transportasi lokal.
     */
    public function notaTransport()
    {
        return $this->hasMany(NotaTransport::class, 'id_usulan')->orderBy('urutan');
    }

    /**
     * Laporan perjalanan dinas yang disusun di aplikasi.
     */
    public function laporan()
    {
        return $this->hasOne(LaporanPerjadin::class, 'id_usulan');
    }

    /**
     * @return HasMany<PesertaUsulan, $this>
     */
    public function peserta(): HasMany
    {
        return $this->hasMany(PesertaUsulan::class, 'id_usulan');
    }

    /**
     * @return BelongsTo<TahunAnggaran, $this>
     */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class, 'id_tahun_anggaran');
    }

    /**
     * @return BelongsTo<LokasiTujuan, $this>
     */
    public function lokasiTujuan(): BelongsTo
    {
        return $this->belongsTo(LokasiTujuan::class, 'id_lokasi');
    }

    /**
     * Daftar pengeluaran riil per peserta perjalanan.
     *
     * @return HasMany<DaftarRiil, $this>
     */
    public function daftarRiil(): HasMany
    {
        return $this->hasMany(DaftarRiil::class, 'id_usulan');
    }

    /**
     * Riwayat keputusan tiap tahap persetujuan berjenjang.
     *
     * @return HasMany<Persetujuan, $this>
     */
    public function persetujuan(): HasMany
    {
        return $this->hasMany(Persetujuan::class, 'id_usulan')->oldest();
    }

    /**
     * Jejak audit usulan ini, terbaru lebih dulu.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_usulan')->latest();
    }

    /**
     * Riwayat tindakan berurut waktu untuk ditampilkan sebagai timeline.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function riwayat(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'id_usulan')->oldest();
    }

    /**
     * Status sebagai enum, sumber tunggal untuk label, warna, dan transisi.
     *
     * Kolom `status` sengaja tetap berupa string agar perbandingan langsung
     * di controller dan Blade yang sudah ada tetap berjalan.
     */
    public function getStatusEnumAttribute(): StatusUsulan
    {
        return StatusUsulan::dari($this->status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status_enum->badge();
    }

    public function getStatusTextAttribute(): string
    {
        return $this->status_enum->label();
    }

    /**
     * Tahap persetujuan yang sedang menunggu keputusan, bila ada.
     */
    public function getLevelBerjalanAttribute(): ?LevelPersetujuan
    {
        return $this->status_enum->level();
    }

    public function sedangMenunggu(): bool
    {
        return $this->status_enum->sedangMenunggu();
    }

    public function bolehDisunting(): bool
    {
        return $this->status_enum->bolehDisunting();
    }

    /**
     * Usulan lain milik pelaksana yang sama untuk perjalanan yang sama —
     * tanggal berangkat dan kembali sama, serta surat tugas atau SPD sama.
     * Biasanya terjadi karena usulan yang sama terkirim dua kali.
     */
    public function kembaran(): ?self
    {
        return self::kembaranUntuk(collect([$this]))[$this->id] ?? null;
    }

    /**
     * Kembaran tiap usulan dalam satu daftar, dicari dengan satu kueri —
     * daftar usulan memeriksanya untuk setiap baris.
     *
     * @param  Collection<int, self>  $daftar
     * @return array<int, self> Id usulan => kembarannya yang paling lama.
     */
    public static function kembaranUntuk(Collection $daftar): array
    {
        if ($daftar->isEmpty()) {
            return [];
        }

        $kunci = fn (self $usulan) => $usulan->id_user.'|'.Carbon::parse($usulan->tanggal_mulai)->toDateString()
            .'|'.Carbon::parse($usulan->tanggal_selesai)->toDateString();

        $calon = self::query()
            ->whereIn('id_user', $daftar->pluck('id_user')->unique()->all())
            ->whereIn('tanggal_mulai', $daftar->map(fn (self $usulan) => $usulan->getRawOriginal('tanggal_mulai'))->unique()->all())
            ->orderBy('id')
            ->get(['id', 'id_user', 'no_usulan', 'tanggal_mulai', 'tanggal_selesai', 'no_tugas', 'id_spd'])
            ->groupBy($kunci);

        $hasil = [];

        foreach ($daftar as $usulan) {
            $kembar = ($calon->get($kunci($usulan)) ?? collect())->first(fn (self $lain) => $lain->id !== $usulan->id
                && ((blank($usulan->no_tugas) && ! $usulan->id_spd)
                    || (filled($usulan->no_tugas) && $lain->no_tugas === $usulan->no_tugas)
                    || ($usulan->id_spd && $lain->id_spd === $usulan->id_spd)));

            if ($kembar) {
                $hasil[$usulan->id] = $kembar;
            }
        }

        return $hasil;
    }

    /**
     * Usulan kembar boleh dihapus pelaksana walau sudah diajukan — selama
     * belum ada uang yang keluar dan berkasnya belum ditandatangani PPK.
     */
    public function bolehDihapusSebagaiDuplikat(): bool
    {
        if ($this->status_enum === StatusUsulan::Selesai || $this->keuangan?->uangMukaTerbayar()) {
            return false;
        }

        if ($this->keuangan?->riwayatPembayaran()->exists()) {
            return false;
        }

        if (DaftarRiil::where('id_usulan', $this->id)->sudahDitandatangani()->exists()) {
            return false;
        }

        return $this->kembaran() !== null;
    }

    /**
     * Usulan ini milik pengguna itu — sebagai pelaksana, pembuatnya, atau
     * peserta rombongannya — atau ia super administrator.
     *
     * Hak "Melihat seluruh usulan" hanya membuka daftarnya; menyunting dan
     * menghapus tetap urusan pihak yang terkait dengan usulan.
     */
    public function bolehDikelolaOleh(User $pengguna): bool
    {
        return $pengguna->isAdmin()
            || $this->id_user === $pengguna->id
            || $this->id_pembuat === $pengguna->id
            || $this->peserta->contains('id_user', $pengguna->id);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeMenungguKeputusan(Builder $query): void
    {
        $query->whereIn('status', StatusUsulan::nilaiMenunggu());
    }

    public function isKelompok(): bool
    {
        return $this->jenis_pengajuan === self::PENGAJUAN_KELOMPOK;
    }

    public function getJenisPengajuanLabelAttribute(): string
    {
        return $this->isKelompok() ? 'Bagian dari rombongan' : 'Personal';
    }

    /**
     * Berkas SPD bertanda tangan yang diunggah pengusul, bila ada.
     */
    public function berkasSpdBertandaTangan(): ?string
    {
        $berkas = $this->dokumen()->latest('id')->value('spd_ditandatangani');

        return filled($berkas) ? $berkas : null;
    }

    /**
     * Usulan sudah membawa SPD yang ditandatangani beserta nomor resminya —
     * dasar persetujuan PPK, jadi tanpa keduanya usulan tidak boleh berjalan.
     */
    public function punyaSpdBertandaTangan(): bool
    {
        return filled($this->no_spd) && $this->berkasSpdBertandaTangan() !== null;
    }

    /**
     * Usulan ini dibuatkan orang lain, sehingga pemiliknya perlu mengonfirmasi.
     */
    public function dibuatkanOrangLain(): bool
    {
        return $this->id_pembuat !== null && $this->id_pembuat !== $this->id_user;
    }

    /**
     * Daftar peserta hanya boleh diubah selama usulannya masih berupa draf
     * milik sendiri.
     *
     * Sekali usulan berlaku, susunan pesertanya menjadi dasar penerbitan SPPD
     * dan pembayaran, jadi dikunci. Usulan yang dibuatkan orang lain juga
     * dikunci karena pesertanya sudah ditetapkan saat rombongan disusun —
     * tiap orang menerima usulan bernomor sendiri.
     */
    public function bolehMengubahPeserta(): bool
    {
        return $this->bolehDisunting() && ! $this->dibuatkanOrangLain();
    }

    /**
     * Alasan daftar pesertanya terkunci, untuk ditampilkan pada halaman detail.
     */
    public function alasanPesertaTerkunci(): ?string
    {
        if ($this->bolehMengubahPeserta()) {
            return null;
        }

        if ($this->dibuatkanOrangLain()) {
            return 'Peserta ditetapkan saat rombongan disusun. Tiap peserta menerima usulan bernomor sendiri, '
                .'jadi susunannya tidak dapat diubah dari sini.';
        }

        return 'Usulan sudah '.mb_strtolower($this->status_enum->label())
            .', sehingga susunan pesertanya terkunci sebagai dasar penerbitan SPPD dan pembayaran.';
    }

    /**
     * Pemilik boleh mengubah kesediaannya selama usulan belum berlaku.
     */
    public function konfirmasiMasihTerbuka(): bool
    {
        return $this->dibuatkanOrangLain()
            && ($this->status_enum->bolehDisunting() || $this->sedangMenunggu());
    }

    public function sudahDikonfirmasi(): bool
    {
        return $this->konfirmasi === self::KONFIRMASI_DIKONFIRMASI;
    }

    public function menungguKonfirmasi(): bool
    {
        return $this->konfirmasi === self::KONFIRMASI_MENUNGGU;
    }

    /**
     * @return array<string, string>
     */
    public static function konfirmasiOptions(): array
    {
        return [
            self::KONFIRMASI_MENUNGGU => 'Menunggu Konfirmasi',
            self::KONFIRMASI_DIKONFIRMASI => 'Bersedia Berangkat',
            self::KONFIRMASI_DIBATALKAN => 'Mengundurkan Diri',
        ];
    }

    public function getKonfirmasiLabelAttribute(): string
    {
        return self::konfirmasiOptions()[$this->konfirmasi] ?? '-';
    }

    public function getKonfirmasiBadgeAttribute(): string
    {
        return match ($this->konfirmasi) {
            self::KONFIRMASI_DIKONFIRMASI => 'bg-emerald-100 text-emerald-700',
            self::KONFIRMASI_DIBATALKAN => 'bg-red-100 text-red-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }

    public function konfirmasiBerangkat(): void
    {
        $this->update([
            'konfirmasi' => self::KONFIRMASI_DIKONFIRMASI,
            'alasan_batal' => null,
            'dikonfirmasi_at' => now(),
        ]);
    }

    public function batalkanKeikutsertaan(?string $alasan = null): void
    {
        $this->update([
            'konfirmasi' => self::KONFIRMASI_DIBATALKAN,
            'alasan_batal' => $alasan,
            'dikonfirmasi_at' => now(),
            'status' => StatusUsulan::Ditolak->value,
            'catatan' => $alasan ? "Dibatalkan oleh pemilik: {$alasan}" : 'Dibatalkan oleh pemilik usulan.',
        ]);
    }

    /**
     * Usulan lain dalam satu rombongan input yang sama.
     *
     * @return HasMany<Usulan, $this>
     */
    public function serombongan(): HasMany
    {
        return $this->hasMany(Usulan::class, 'kode_rombongan', 'kode_rombongan')
            ->whereKeyNot($this->getKey());
    }

    /**
     * Pengguna yang membuatkan usulan ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pembuat');
    }

    /**
     * Rentang tanggal perjalanan dengan nama bulan berbahasa Indonesia —
     * dulu memakai date() yang selalu berbahasa Inggris ("Aug", "Dec").
     */
    public function getPeriodeAttribute(): string
    {
        return $this->tanggal_mulai_formatted.' — '.$this->tanggal_selesai_formatted;
    }

    public function getTanggalMulaiFormattedAttribute(): string
    {
        return Carbon::parse($this->tanggal_mulai)->translatedFormat('d M Y');
    }

    public function getTanggalSelesaiFormattedAttribute(): string
    {
        return Carbon::parse($this->tanggal_selesai)->translatedFormat('d M Y');
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

        // Satu definisi kelengkapan untuk seluruh aplikasi. Bila diperiksa
        // ulang di sini, cepat atau lambat pengingat menagih berkas yang
        // menurut halaman ini sudah lengkap.
        if (! app(PenagihDokumen::class)->lengkap($this)) {
            return false;
        }

        $this->update(['status' => 'selesai']);

        return true;
    }
}
