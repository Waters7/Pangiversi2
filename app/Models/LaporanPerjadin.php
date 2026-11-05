<?php

namespace App\Models;

use App\Enums\BagianLaporan;
use App\Enums\StatusLaporanPerjadin;
use App\Enums\StatusTindakLanjut;
use App\Services\FormatLaporanPerjadin;
use App\Services\PustakaBerkasBersama;
use App\Services\PustakaSuratTugas;
use App\Services\TautanVerifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /** Format lama: uraian per hari dan rencana tindak lanjut dalam satu tabel. */
    public const FORMAT_LAMA = 1;

    /** Format Kemenkes: Pendahuluan, kegiatan, hasil, simpulan, penutup, dan lampiran dokumentasi. */
    public const FORMAT_KEMENKES = 2;

    /** Awalan nomor laporan: LPD-tahun-urut, misalnya LPD-2026-0012. */
    public const AWALAN_NOMOR = 'LPD-';

    protected $fillable = [
        'id_usulan',
        'nomor_laporan',
        'id_laporan_acuan',
        'versi_format',
        'tentang',
        'umum',
        'maksud_tujuan',
        'ruang_lingkup',
        'dasar',
        'kegiatan_dilaksanakan',
        'hasil',
        'penutup',
        'id_status_hasil',
        'kesimpulan',
        'berkas_laporan',
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
            'versi_format' => 'integer',
            'diselesaikan_at' => 'datetime',
            'dikirim_at' => 'datetime',
            'dikonfirmasi_at' => 'datetime',
            'dikembalikan_at' => 'datetime',
        ];
    }

    /** Nomor laporan terbit begitu laporannya dibuat. */
    protected static function booted(): void
    {
        static::creating(function (self $laporan): void {
            $laporan->nomor_laporan ??= self::nomorBerikutnya();
        });
    }

    /**
     * Laporan sebuah perjalanan, dibuat bila belum ada.
     *
     * Dua laporan yang dibuat bersamaan dapat memperoleh nomor urut yang
     * sama; indeks unik menolak yang belakangan, lalu nomornya diambil ulang.
     */
    public static function untukUsulan(Usulan $usulan): self
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return self::firstOrCreate(['id_usulan' => $usulan->id]);
            } catch (UniqueConstraintViolationException $galat) {
                if ($percobaan >= 3) {
                    throw $galat;
                }
            }
        }
    }

    /** Nomor urut berikutnya pada tahun berjalan. */
    public static function nomorBerikutnya(): string
    {
        $awalan = self::AWALAN_NOMOR.now()->format('Y').'-';

        // Diurutkan menurut panjangnya dulu supaya urutan di atas 9999 tetap benar.
        $terakhir = self::query()
            ->where('nomor_laporan', 'like', $awalan.'%')
            ->orderByRaw('LENGTH(nomor_laporan) DESC')
            ->orderByDesc('nomor_laporan')
            ->value('nomor_laporan');

        $urut = $terakhir ? (int) substr($terakhir, strlen($awalan)) + 1 : 1;

        return $awalan.str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    /** Laporan bernomor ini, tanpa membedakan huruf besar dan spasi. */
    public static function denganNomor(?string $nomor): ?self
    {
        $nomor = mb_strtoupper(str_replace(' ', '', trim((string) $nomor)));

        return $nomor === '' ? null : self::with('usulan.user')->firstWhere('nomor_laporan', $nomor);
    }

    /**
     * @return BelongsTo<Usulan, $this>
     */
    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class, 'id_usulan');
    }

    /**
     * Laporan rekan setugas yang isinya dipakai laporan ini.
     *
     * @return BelongsTo<LaporanPerjadin, $this>
     */
    public function acuan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_laporan_acuan');
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
     * @return HasMany<LaporanDokumentasi, $this>
     */
    public function dokumentasi(): HasMany
    {
        return $this->hasMany(LaporanDokumentasi::class, 'id_laporan');
    }

    /**
     * Laporan ini memakai format Kemenkes. Laporan yang sudah diisi dengan
     * format lama sebelum format ini berlaku tetap pada format lamanya.
     */
    public function formatKemenkes(): bool
    {
        return (int) ($this->versi_format ?? self::FORMAT_KEMENKES) >= self::FORMAT_KEMENKES;
    }

    /** Isi satu bagian laporan format Kemenkes. */
    public function isiBagian(BagianLaporan $bagian): ?string
    {
        $isi = $this->{$bagian->kolom()};

        return filled($isi) ? (string) $isi : null;
    }

    /**
     * Baris rencana tindak lanjut untuk formulir: kiriman terakhir bila
     * penyimpanannya ditolak, selain itu yang tersimpan, sedikitnya satu baris.
     *
     * @param  array<int, array<string, mixed>>|null  $kiriman
     * @return list<array{uraian: string, penanggung_jawab: string, target_selesai: string, status: string}>
     */
    public function barisTindakLanjutFormulir(?array $kiriman = null): array
    {
        $baris = $this->tindakLanjut->map(fn (TindakLanjut $tindak) => [
            'uraian' => $tindak->uraian,
            'penanggung_jawab' => $tindak->penanggung_jawab,
            'target_selesai' => $tindak->target_selesai?->toDateString(),
            'status' => $tindak->status->value,
        ])->all();

        return array_map(fn (array $isi) => [
            'uraian' => (string) ($isi['uraian'] ?? ''),
            'penanggung_jawab' => (string) ($isi['penanggung_jawab'] ?? ''),
            'target_selesai' => (string) ($isi['target_selesai'] ?? ''),
            'status' => (string) ($isi['status'] ?? StatusTindakLanjut::Rencana->value),
        ], array_values($kiriman ?? $baris) ?: [[]]);
    }

    /** Foto dokumentasi satu bagian, bila ada. */
    public function dokumentasiBagian(BagianLaporan $bagian): ?LaporanDokumentasi
    {
        return $this->dokumentasi->first(fn (LaporanDokumentasi $foto) => $foto->bagian === $bagian);
    }

    /**
     * Foto dokumentasi untuk halaman lampiran, urut menurut bagian laporan
     * dan disematkan sebagai data URI supaya terbaca oleh pembuat PDF.
     *
     * @return list<array{nomor: int, bagian: BagianLaporan, gambar: string}>
     */
    public function lampiranDokumentasi(): array
    {
        $disk = Storage::disk('public');
        $lampiran = [];

        foreach (BagianLaporan::cases() as $bagian) {
            $foto = $this->dokumentasiBagian($bagian);

            if (! $foto || ! $disk->exists($foto->berkas)) {
                continue;
            }

            $jenis = strtolower(pathinfo($foto->berkas, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg';

            $lampiran[] = [
                'nomor' => count($lampiran) + 1,
                'bagian' => $bagian,
                'gambar' => 'data:'.$jenis.';base64,'.base64_encode($disk->get($foto->berkas)),
            ];
        }

        return $lampiran;
    }

    /**
     * Bagian wajib format Kemenkes yang belum diisi, beserta judulnya.
     *
     * @return list<string>
     */
    public function bagianWajibKosong(): array
    {
        $kosong = blank($this->tentang) ? ['Tentang (nama kegiatan)'] : [];

        foreach (BagianLaporan::cases() as $bagian) {
            if ($bagian->wajib() && $this->isiBagian($bagian) === null) {
                $kosong[] = $bagian->label();
            }
        }

        if ($this->id_status_hasil === null) {
            $kosong[] = 'Status hasil yang dicapai';
        }

        return $kosong;
    }

    /**
     * Laporan layak diselesaikan. Format lama: sedikitnya satu uraian
     * kegiatan, satu rencana tindak lanjut, dan status hasilnya dipilih.
     * Format Kemenkes: judul "tentang", kegiatan yang dilaksanakan, hasil
     * yang dicapai, simpulan dan saran, serta status hasilnya terisi.
     */
    public function layakDiselesaikan(): bool
    {
        if ($this->formatKemenkes()) {
            return $this->bagianWajibKosong() === [];
        }

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

    // ── Memakai laporan rekan setugas ──

    /**
     * Mengapa laporan ini tidak dapat dipakai oleh laporan lain, atau null
     * bila boleh: nomor surat tugas, waktu pelaksanaan, dan lokasi tugasnya
     * harus sama, keduanya format Kemenkes, dan bagian wajibnya sudah terisi.
     */
    public function alasanTidakDapatDipakaiOleh(self $pemakai): ?string
    {
        $this->loadMissing('usulan');
        $pemakai->loadMissing('usulan');

        $asal = $this->usulan;
        $tujuan = $pemakai->usulan;
        $nomor = $this->nomor_laporan;

        return match (true) {
            $this->id === $pemakai->id || $this->id_usulan === $pemakai->id_usulan => 'Nomor '.$nomor.' adalah nomor laporan ini sendiri.',
            ! $asal || ! $tujuan => 'Perjalanan dinas laporan '.$nomor.' tidak ditemukan.',
            ! $this->formatKemenkes() || ! $pemakai->formatKemenkes() => 'Laporan '.$nomor.' memakai format lama, isinya tidak dapat dipakai pada format Kemenkes.',
            ! $tujuan->suratTugasSamaDengan($asal) => 'Surat tugasnya berbeda: laporan '.$nomor.' berdasar surat tugas '.($asal->no_tugas ?: '—')
                .', sedangkan perjalanan ini '.(filled($tujuan->no_tugas) ? 'surat tugas '.$tujuan->no_tugas : 'belum mencatat nomor surat tugas').'.',
            ! $tujuan->waktuSamaDengan($asal) => 'Waktu pelaksanaannya berbeda: laporan '.$nomor.' untuk perjalanan '.$asal->periode
                .', sedangkan perjalanan ini '.$tujuan->periode.'.',
            ! $tujuan->lokasiSamaDengan($asal) => 'Lokasi tugasnya berbeda: laporan '.$nomor.' untuk tugas di '.($asal->lokasi ?: '—')
                .', sedangkan perjalanan ini ke '.($tujuan->lokasi ?: '—').'.',
            $this->bagianWajibKosong() !== [] => 'Laporan '.$nomor.' belum lengkap diisi pelaksananya: '.implode(', ', $this->bagianWajibKosong()).'.',
            default => null,
        };
    }

    /**
     * Laporan rekan setugas yang isinya dapat dipakai laporan ini — nomor
     * surat tugas, waktu pelaksanaan, dan lokasinya sama — tidak termasuk
     * yang justru memakai isi laporan ini.
     *
     * @return Collection<int, LaporanPerjadin>
     */
    public static function acuanTersediaUntuk(self $pemakai): Collection
    {
        $pemakai->loadMissing('usulan');
        $kunci = PustakaSuratTugas::kunci($pemakai->usulan?->no_tugas);

        if ($kunci === '') {
            return new Collection;
        }

        return self::query()
            ->where('id', '!=', $pemakai->id)
            ->where('versi_format', '>=', self::FORMAT_KEMENKES)
            ->where(fn (Builder $q) => $q->whereNull('id_laporan_acuan')->orWhere('id_laporan_acuan', '!=', $pemakai->id))
            ->whereHas('usulan', fn (Builder $q) => $q
                ->where('id', '!=', $pemakai->id_usulan)
                ->whereRaw(PustakaSuratTugas::kunciSql('no_tugas').' = ?', [$kunci]))
            ->with('usulan.user')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $acuan) => $acuan->alasanTidakDapatDipakaiOleh($pemakai) === null)
            ->values();
    }

    /**
     * Pakai isi laporan rekan setugas: judul, uraian tiap bagian, status
     * hasil, rencana tindak lanjut, dan foto dokumentasi menggantikan isian
     * laporan ini. Berkas laporan ikut bila rekannya mengunggah; bila tidak,
     * berkas milik sendiri dibiarkan.
     *
     * Foto dan berkas disalin, bukan dirujuk, supaya mengganti atau
     * menghapusnya pada laporan yang satu tidak ikut menghapus milik
     * rekannya. Laporan ini tetap dikirim dan ditandatangani pelaksananya
     * sendiri.
     */
    public function salinIsiDari(self $acuan): void
    {
        $acuan->loadMissing('tindakLanjut', 'dokumentasi');
        $pustaka = app(PustakaBerkasBersama::class);

        $fotoBaru = [];
        foreach ($acuan->dokumentasi as $foto) {
            if ($berkas = $pustaka->salin($foto->berkas, 'dokumen/laporan-dokumentasi')) {
                $fotoBaru[$foto->bagian->value] = $berkas;
            }
        }

        $berkasBaru = $pustaka->salin($acuan->berkas_laporan, 'dokumen/laporan');
        $dilepas = $this->dokumentasi()->pluck('berkas')->all();

        if ($berkasBaru && filled($this->berkas_laporan)) {
            $dilepas[] = $this->berkas_laporan;
        }

        DB::transaction(function () use ($acuan, $fotoBaru, $berkasBaru): void {
            $isian = [
                'tentang' => $acuan->tentang,
                'id_status_hasil' => $acuan->id_status_hasil,
                'id_laporan_acuan' => $acuan->id,
            ];

            foreach (BagianLaporan::cases() as $bagian) {
                $isian[$bagian->kolom()] = $acuan->{$bagian->kolom()};
            }

            $this->update($isian + ($berkasBaru ? ['berkas_laporan' => $berkasBaru] : []));

            $this->tindakLanjut()->delete();
            foreach ($acuan->tindakLanjut->values() as $urutan => $tindak) {
                $this->tindakLanjut()->create([
                    'urutan' => $urutan + 1,
                    'uraian' => $tindak->uraian,
                    'penanggung_jawab' => $tindak->penanggung_jawab,
                    'target_selesai' => $tindak->target_selesai,
                    'status' => $tindak->status,
                ]);
            }

            $this->dokumentasi()->delete();
            foreach ($fotoBaru as $bagian => $berkas) {
                $this->dokumentasi()->create(['bagian' => $bagian, 'berkas' => $berkas]);
            }
        });

        if ($dilepas !== []) {
            Storage::disk('public')->delete($dilepas);
        }

        $this->unsetRelation('tindakLanjut')->unsetRelation('dokumentasi');
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
