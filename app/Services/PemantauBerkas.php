<?php

namespace App\Services;

use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\Usulan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Keadaan lengkap satu berkas pelaksana untuk dipantau sendiri: sampai di
 * mana tanda tangannya — tim keuangan, pelaksana, PPK, Direktur atas
 * laporan, dan daftar nominatif — serta sudah dibayar apa saja: uang muka,
 * pelunasan, dan penggantian transport lokal.
 *
 * Tiap butir berkeadaan `selesai`, `menunggu`, `perhatian` (disanggah atau
 * dikembalikan), atau `tidak-perlu` (misalnya transport lokal yang memang
 * tidak ada), dilengkapi waktu, pelaku, dan keterangan pendek supaya
 * pelaksana tahu berkasnya berhenti di mana tanpa bertanya ke tim keuangan.
 */
class PemantauBerkas
{
    public const SELESAI = 'selesai';

    public const MENUNGGU = 'menunggu';

    public const PERHATIAN = 'perhatian';

    public const TIDAK_PERLU = 'tidak-perlu';

    /**
     * Daftar nominatif yang sudah dimuat, bertaut nomor surat tugasnya.
     *
     * @var Collection<string, DaftarNominatif>|null
     */
    private ?Collection $nominatif = null;

    public function __construct(private PenyusunNominatif $penyusun) {}

    /**
     * Muat sekali seluruh yang dibutuhkan sekumpulan berkas, supaya halaman
     * daftar tidak menarik nominatif dan laporan tiap baris sendiri-sendiri.
     *
     * @param  Collection<int, DaftarRiil>  $berkas
     */
    public function siapkan(Collection $berkas): void
    {
        $berkas->loadMissing([
            'usulan.laporan.pimpinan', 'usulan.keuangan.rincianBiaya', 'usulan.daftarRiil',
            // Dibaca PenagihDokumen saat menilai apakah pelaksana sudah tercantum di nominatif.
            'usulan.dokumen', 'usulan.tiket', 'usulan.notaTransport', 'usulan.kategoriPerjadin',
            'ppk', 'validator', 'pembayar',
        ]);

        $this->nominatif = DaftarNominatif::with('ppk')
            ->whereIn('no_tugas', $berkas->pluck('usulan.no_tugas')->filter()->unique()->all())
            ->get()
            ->keyBy('no_tugas');
    }

    /**
     * @return array{tanda_tangan: list<array<string, mixed>>, pembayaran: list<array<string, mixed>>}
     */
    public function untuk(DaftarRiil $berkas): array
    {
        $berkas->loadMissing(['usulan.laporan.pimpinan', 'usulan.keuangan', 'ppk', 'validator', 'pembayar']);

        return [
            'tanda_tangan' => $this->tandaTangan($berkas),
            'pembayaran' => $this->pembayaran($berkas),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tandaTangan(DaftarRiil $berkas): array
    {
        $usulan = $berkas->usulan;
        $rincian = $berkas->jalurRincian();
        $riil = $berkas->jalur(JalurPersetujuan::RIIL);

        return [
            $this->butir(
                'Dicek tim keuangan',
                $berkas->sudahDivalidasi() || $rincian->sudahDicekTimKeuangan() ? self::SELESAI : self::MENUNGGU,
                $berkas->divalidasi_at,
                $berkas->validator?->nama,
                $berkas->sudahDivalidasi() || $rincian->sudahDicekTimKeuangan() ? 'Nominal dinyatakan benar' : 'Nominal masih diperiksa',
            ),
            $this->butir(
                'Dikirim ke Anda',
                $berkas->sudahDikirimKePegawai() ? self::SELESAI : self::MENUNGGU,
                $berkas->dikirim_ke_pegawai_at,
                null,
                $berkas->sudahDikirimKePegawai()
                    ? ($berkas->batas_sanggah ? 'Masa sanggah sampai '.$berkas->batas_sanggah->translatedFormat('d M Y') : null)
                    : 'Menunggu dikirim tim keuangan',
            ),
            $this->butirJalur('Rincian biaya · tanda tangan Anda', $rincian, 'pelaksana'),
            $this->butirJalur('Rincian biaya · tanda tangan PPK', $rincian, 'ppk'),
            ...($berkas->berlaku()
                ? [
                    $this->butirJalur('Daftar riil · tanda tangan Anda', $riil, 'pelaksana'),
                    $this->butirJalur('Daftar riil · tanda tangan PPK', $riil, 'ppk'),
                ]
                : [$this->butir('Daftar riil transport lokal', self::TIDAK_PERLU, null, null, 'Tidak ada transport lokal — tidak perlu ditandatangani')]),
            $this->butirLaporan($usulan),
            ...$this->butirNominatif($usulan),
        ];
    }

    /**
     * Satu sisi tanda tangan pada sebuah jalur: pelaksana atau PPK.
     *
     * @return array<string, mixed>
     */
    private function butirJalur(string $label, JalurPersetujuan $jalur, string $sisi): array
    {
        if ($sisi === 'pelaksana') {
            return match (true) {
                $jalur->sudahDisetujui() => $this->butir($label, self::SELESAI, $jalur->waktu('disetujui'), null,
                    $jalur->kodeKonfirmasi() ? 'Kode konfirmasi '.$jalur->kodeKonfirmasi() : null),
                $jalur->sedangDisanggah() => $this->butir($label, self::PERHATIAN, $jalur->waktu('disanggah'), null,
                    'Anda menyanggah — kembali ke tim keuangan'),
                $jalur->bolehDitandatanganiPelaksana() => $this->butir($label, self::MENUNGGU, null, null, 'Menunggu tanda tangan Anda'),
                default => $this->butir($label, self::MENUNGGU, null, null, 'Menunggu berkas dikirim'),
            };
        }

        return match (true) {
            $jalur->sudahDitandatangani() => $this->butir($label, self::SELESAI, $jalur->waktu('ditandatangani'), $jalur->ppk()?->nama,
                $jalur->kodeVerifikasi() ? 'Kode verifikasi '.$jalur->kodeVerifikasi() : null),
            $jalur->sudahDisetujui() => $this->butir($label, self::MENUNGGU, null, null, 'Menunggu PPK'),
            default => $this->butir($label, self::MENUNGGU, null, null, 'Setelah tanda tangan Anda'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function butirLaporan(?Usulan $usulan): array
    {
        $laporan = $usulan?->laporan;
        $label = 'Laporan perjadin · konfirmasi Direktur';

        return match (true) {
            $laporan?->sudahDikonfirmasi() => $this->butir($label, self::SELESAI, $laporan->dikonfirmasi_at, $laporan->pimpinan?->nama,
                'Syarat pelunasan terpenuhi'),
            $laporan?->perluRevisi() => $this->butir($label, self::PERHATIAN, $laporan->dikembalikan_at, $laporan->pimpinan?->nama,
                'Dikembalikan pimpinan — perlu direvisi'),
            $laporan?->sudahDikirim() => $this->butir($label, self::MENUNGGU, $laporan->diselesaikan_at, null, 'Menunggu konfirmasi Direktur'),
            default => $this->butir($label, self::MENUNGGU, null, null, 'Laporan belum dikirim ke pimpinan'),
        };
    }

    /**
     * Daftar nominatif surat tugasnya: terbit, ditandatangani PPK, dan
     * diterima tim keuangan — dengan catatan pelaksana ini baru tercantum
     * setelah kedua dokumennya disahkan PPK.
     *
     * @return list<array<string, mixed>>
     */
    private function butirNominatif(?Usulan $usulan): array
    {
        $nominatif = $usulan ? $this->nominatifUntuk($usulan) : null;
        $tercantum = $usulan !== null && $nominatif !== null && $this->penyusun->usulanSiap($usulan);

        if ($nominatif === null) {
            return [$this->butir('Daftar nominatif', self::MENUNGGU, null, null,
                'Terbit setelah kedua dokumen ditandatangani PPK')];
        }

        if (! $tercantum) {
            return [$this->butir('Daftar nominatif', self::MENUNGGU, $nominatif->created_at, null,
                'Daftar sudah terbit; Anda tercantum setelah kedua dokumen ditandatangani PPK')];
        }

        return [
            $this->butir('Daftar nominatif · terbit', self::SELESAI, $nominatif->created_at, null, 'Anda tercantum di dalamnya'),
            $this->butir('Daftar nominatif · tanda tangan PPK',
                $nominatif->sudahDitandatangani() ? self::SELESAI : self::MENUNGGU,
                $nominatif->ditandatangani_at, $nominatif->ppk?->nama,
                $nominatif->sudahDitandatangani() ? null : 'Menunggu PPK'),
            $this->butir('Daftar nominatif · diterima tim keuangan',
                $nominatif->sudahDikirim() ? self::SELESAI : self::MENUNGGU,
                $nominatif->dikirim_at, null,
                $nominatif->sudahDikirim() ? null : 'Menunggu dikirim PPK'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pembayaran(DaftarRiil $berkas): array
    {
        $usulan = $berkas->usulan;
        $keuangan = $usulan?->keuangan;

        return [
            $this->butirUangMuka($keuangan),
            $this->butirPelunasan($usulan, $keuangan),
            $this->butirTransport($berkas, $keuangan),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function butirUangMuka(?Keuangan $keuangan): array
    {
        if ($keuangan?->uangMukaTerbayar()) {
            return $this->butir('Uang muka', self::SELESAI, $keuangan->tanggal_transfer, null, null, $keuangan->uang_muka);
        }

        return $this->butir('Uang muka', self::MENUNGGU, null, null, 'Menunggu bendahara', $keuangan?->uang_muka);
    }

    /**
     * @return array<string, mixed>
     */
    private function butirPelunasan(?Usulan $usulan, ?Keuangan $keuangan): array
    {
        if ($keuangan?->sudahLunas()) {
            return $this->butir('Pelunasan', self::SELESAI, $keuangan->tanggal_pelunasan, null,
                $keuangan->kode_konfirmasi_bayar ? 'Kode bendahara '.$keuangan->kode_konfirmasi_bayar : null, $keuangan->sisa);
        }

        $laporan = $usulan?->laporan;

        return $this->butir('Pelunasan', self::MENUNGGU, null, null, match (true) {
            ! $keuangan?->uangMukaTerbayar() => 'Setelah uang muka dibayarkan',
            ! $laporan?->sudahDikonfirmasi() => 'Menunggu konfirmasi laporan oleh Direktur',
            default => 'Syarat terpenuhi — menunggu bendahara',
        }, $keuangan?->sisa);
    }

    /**
     * Penggantian transport lokal dibayar terpisah lewat menu Pembayaran,
     * atau ikut dalam pelunasan bila daftar riilnya sudah disahkan sebelum
     * pelunasan dibayarkan.
     *
     * @return array<string, mixed>
     */
    private function butirTransport(DaftarRiil $berkas, ?Keuangan $keuangan): array
    {
        $label = 'Transport lokal';

        if (! $berkas->berlaku()) {
            return $this->butir($label, self::TIDAK_PERLU, null, null, 'Tidak ada transport lokal');
        }

        if ($berkas->sudahDibayar()) {
            return $this->butir($label, self::SELESAI, $berkas->dibayar_at, $berkas->pembayar?->nama, 'Dibayar terpisah', $berkas->total_riil);
        }

        $ikutPelunasan = $keuangan?->sudahLunas()
            && $berkas->sudah_ditandatangani
            && $berkas->ditandatangani_at !== null
            && $keuangan->tanggal_pelunasan !== null
            && $berkas->ditandatangani_at->lte($keuangan->tanggal_pelunasan->copy()->endOfDay());

        if ($ikutPelunasan) {
            return $this->butir($label, self::SELESAI, $keuangan->tanggal_pelunasan, null, 'Dibayar bersama pelunasan', $berkas->total_riil);
        }

        return $this->butir($label, self::MENUNGGU, null, null,
            $berkas->sudah_ditandatangani ? 'Menunggu pembayaran bendahara' : 'Setelah daftar riil ditandatangani PPK',
            $berkas->total_riil);
    }

    /**
     * @return array{label: string, keadaan: string, waktu: ?CarbonInterface, oleh: ?string, keterangan: ?string, nominal: ?float}
     */
    private function butir(string $label, string $keadaan, ?CarbonInterface $waktu, ?string $oleh, ?string $keterangan, ?float $nominal = null): array
    {
        return compact('label', 'keadaan', 'waktu', 'oleh', 'keterangan', 'nominal');
    }

    private function nominatifUntuk(Usulan $usulan): ?DaftarNominatif
    {
        if (! $usulan->no_tugas) {
            return null;
        }

        if ($this->nominatif !== null) {
            return $this->nominatif->get($usulan->no_tugas);
        }

        return DaftarNominatif::with('ppk')->firstWhere('no_tugas', $usulan->no_tugas);
    }
}
