<?php

namespace App\Services;

use App\Models\DaftarRiil;
use App\Models\User;

/**
 * Satu jalur persetujuan atas satu dokumen pertanggungjawaban.
 *
 * Rincian biaya perjalanan dinas (Lampiran II) dan daftar pengeluaran riil
 * (Lampiran IX) dikirim bersamaan oleh tim keuangan, tetapi disikapi
 * sendiri-sendiri: pelaksana boleh menandatangani yang satu dan menyanggah
 * yang lain, dan PPK menandatangani keduanya terpisah.
 *
 * Keduanya menumpang satu baris `daftar_riil` — kolom tanpa awalan milik
 * daftar riil, kolom berawalan `rincian_` milik rincian biaya — sehingga
 * masa sanggah, validasi tim keuangan, dan pengembalian PPK tetap satu
 * paket sebagaimana alur kerjanya.
 */
class JalurPersetujuan
{
    public const RIIL = 'riil';

    public const RINCIAN = 'rincian';

    /**
     * Nama kolom tiap jalur, dipetakan dari peran kolomnya.
     *
     * @var array<string, array<string, string>>
     */
    private const KOLOM = [
        self::RIIL => [
            'disetujui' => 'disetujui_pegawai_at',
            'disanggah' => 'disanggah_at',
            'sanggahan' => 'sanggahan',
            'konfirmasi' => 'kode_konfirmasi',
            'ditandatangani' => 'ditandatangani_at',
            'ppk' => 'id_ppk',
            'verifikasi' => 'kode_verifikasi',
        ],
        self::RINCIAN => [
            'disetujui' => 'rincian_disetujui_at',
            'disanggah' => 'rincian_disanggah_at',
            'sanggahan' => 'rincian_sanggahan',
            'konfirmasi' => 'rincian_kode_konfirmasi',
            'ditandatangani' => 'rincian_ditandatangani_at',
            'ppk' => 'rincian_id_ppk',
            'verifikasi' => 'rincian_kode_verifikasi',
        ],
    ];

    public function __construct(
        private DaftarRiil $berkas,
        private string $jenis,
    ) {}

    /**
     * Jenis yang dikenali, untuk menyaring masukan dari rute.
     *
     * @return array<int, string>
     */
    public static function jenisTersedia(): array
    {
        return array_keys(self::KOLOM);
    }

    public static function kenali(?string $jenis): string
    {
        return in_array($jenis, self::jenisTersedia(), true) ? $jenis : self::RIIL;
    }

    /**
     * Nama kolom satu jalur, agar kueri di luar kelas ini menyebut kolom
     * yang sama dengan yang dibaca di sini.
     *
     * @return array<string, string>
     */
    public static function kolom(?string $jenis = null): array
    {
        return self::KOLOM[self::kenali($jenis)];
    }

    public function jenis(): string
    {
        return $this->jenis;
    }

    public function berkas(): DaftarRiil
    {
        return $this->berkas;
    }

    public function nama(): string
    {
        return $this->jenis === self::RINCIAN
            ? 'Rincian Biaya Perjalanan Dinas'
            : 'Daftar Pengeluaran Riil';
    }

    /**
     * Nominal yang tertera pada dokumen jalur ini.
     */
    public function total(): float
    {
        return $this->jenis === self::RINCIAN
            ? $this->berkas->totalRincianBiaya()
            : (float) $this->berkas->total_riil;
    }

    // ── Keadaan ──

    public function sudahDisetujui(): bool
    {
        return $this->waktu('disetujui') !== null;
    }

    public function sedangDisanggah(): bool
    {
        return $this->waktu('disanggah') !== null && ! $this->sudahDisetujui();
    }

    public function sudahDitandatangani(): bool
    {
        return $this->waktu('ditandatangani') !== null;
    }

    public function sanggahan(): ?string
    {
        return $this->berkas->{self::KOLOM[$this->jenis]['sanggahan']};
    }

    public function kodeKonfirmasi(): ?string
    {
        return $this->berkas->{self::KOLOM[$this->jenis]['konfirmasi']};
    }

    public function kodeVerifikasi(): ?string
    {
        return $this->berkas->{self::KOLOM[$this->jenis]['verifikasi']};
    }

    public function waktu(string $peran): mixed
    {
        return $this->berkas->{self::KOLOM[$this->jenis][$peran]};
    }

    public function ppk(): ?User
    {
        $id = $this->berkas->{self::KOLOM[$this->jenis]['ppk']};

        return $id ? User::find($id) : null;
    }

    /**
     * Masa sanggah dibuka bersamaan untuk kedua dokumen, tetapi tertutup
     * sendiri-sendiri begitu jalurnya disikapi.
     */
    public function masaSanggahBerjalan(): bool
    {
        return $this->berkas->sudahDikirimKePegawai()
            && ! $this->sudahDisetujui()
            && $this->berkas->batas_sanggah !== null
            && ! today()->greaterThan($this->berkas->batas_sanggah);
    }

    public function sanggahKedaluwarsa(): bool
    {
        return $this->berkas->sudahDikirimKePegawai()
            && ! $this->sudahDisetujui()
            && ! $this->sedangDisanggah()
            && $this->berkas->batas_sanggah !== null
            && today()->greaterThan($this->berkas->batas_sanggah);
    }

    /**
     * PPK hanya membubuhkan tanda tangan setelah pelaksana menyetujui, atau
     * setelah masa sanggah lewat tanpa keberatan.
     */
    public function siapDitandatanganiPpk(): bool
    {
        return $this->total() > 0
            && ! $this->sedangDisanggah()
            && ($this->sudahDisetujui() || $this->sanggahKedaluwarsa());
    }

    // ── Tindakan ──

    public function setujui(): void
    {
        $this->simpan([
            'disetujui' => now(),
            'konfirmasi' => $this->kodeKonfirmasi() ?? DaftarRiil::buatKodeKonfirmasi(),
            'sanggahan' => null,
            'disanggah' => null,
        ]);
    }

    public function sanggah(string $alasan): void
    {
        $this->simpan([
            'sanggahan' => $alasan,
            'disanggah' => now(),
            'disetujui' => null,
            // Persetujuan ditarik kembali, kodenya ikut gugur.
            'konfirmasi' => null,
        ]);
    }

    public function tandaTangani(User $ppk): void
    {
        $this->simpan([
            'ppk' => $ppk->id,
            'ditandatangani' => now(),
            'verifikasi' => $this->kodeVerifikasi() ?? DaftarRiil::buatKodeVerifikasi(),
        ]);

        if ($this->jenis === self::RIIL) {
            $this->berkas->update([
                'diajukan_at' => $this->berkas->diajukan_at ?? $this->berkas->created_at ?? now(),
            ]);
        }
    }

    public function batalkanTandaTangan(): void
    {
        $this->simpan(['ppk' => null, 'ditandatangani' => null, 'verifikasi' => null]);
    }

    /**
     * Kosongkan jalur ini agar pelaksana menyikapinya dari awal — dipakai
     * saat tim keuangan mengirim ulang berkasnya.
     */
    public function bukaUlang(): void
    {
        $this->simpan([
            'disetujui' => null,
            'konfirmasi' => null,
            'sanggahan' => null,
            'disanggah' => null,
        ]);
    }

    // ── Tampilan ──

    public function statusLabel(): string
    {
        return match (true) {
            $this->sudahDitandatangani() => 'Ditandatangani PPK',
            $this->berkas->sedangDikembalikan() => 'Dikembalikan ke Tim Keuangan',
            $this->sedangDisanggah() => 'Disanggah Pelaksana',
            $this->sudahDisetujui() => 'Disetujui Pelaksana',
            $this->sanggahKedaluwarsa() => 'Masa Sanggah Berakhir',
            $this->masaSanggahBerjalan() => 'Menunggu Tanggapan Pelaksana',
            $this->total() > 0 => 'Menunggu Verifikasi Tim Keuangan',
            default => 'Nominal Belum Diisi',
        };
    }

    public function statusBadge(): string
    {
        return match (true) {
            $this->sudahDitandatangani() => 'bg-emerald-100 text-emerald-700',
            $this->berkas->sedangDikembalikan() => 'bg-orange-100 text-orange-700',
            $this->sedangDisanggah() => 'bg-red-100 text-red-700',
            $this->sudahDisetujui(), $this->sanggahKedaluwarsa() => 'bg-teal-100 text-teal-700',
            $this->masaSanggahBerjalan() => 'bg-amber-100 text-amber-700',
            $this->total() > 0 => 'bg-blue-100 text-blue-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * Kelompok kerja yang dipakai menyusun tab pada daftar pelaksana.
     */
    public function kelompok(): string
    {
        return match (true) {
            $this->sudahDitandatangani() => 'selesai',
            $this->sedangDisanggah() => 'disanggah',
            $this->sudahDisetujui() => 'menunggu-ppk',
            $this->masaSanggahBerjalan() => 'perlu-tanggapan',
            default => 'lainnya',
        };
    }

    /**
     * Terjemahkan peran kolom menjadi nama kolom sungguhan lalu simpan.
     *
     * @param  array<string, mixed>  $isi
     */
    private function simpan(array $isi): void
    {
        $baris = [];

        foreach ($isi as $peran => $nilai) {
            $baris[self::KOLOM[$this->jenis][$peran]] = $nilai;
        }

        $this->berkas->update($baris);
    }
}
