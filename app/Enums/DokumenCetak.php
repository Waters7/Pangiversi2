<?php

namespace App\Enums;

use App\Services\KertasCetak;

/**
 * Dokumen cetak yang tampilannya dapat diatur dari Administrasi Sistem.
 *
 * Tiap dokumen mendaftarkan elemen apa saja yang boleh disembunyikan dan
 * teks mana yang boleh diubah. Penanda yang diganti SRIKANDI —
 * ${nomor_naskah} dan ${ttd_pengirim1/2} — sengaja tidak didaftarkan:
 * menghilangkannya membuat surat tidak dapat ditandatangani elektronik.
 */
enum DokumenCetak: string
{
    case Perjadin = 'perjadin';

    case RincianBiaya = 'rincian-biaya';

    case DaftarRiil = 'daftar-riil';

    case DaftarNominatif = 'daftar-nominatif';

    public function label(): string
    {
        return match ($this) {
            self::Perjadin => 'Dokumen Perjadin (SPD)',
            self::RincianBiaya => 'Rincian Biaya',
            self::DaftarRiil => 'Daftar Pengeluaran Riil',
            self::DaftarNominatif => 'Daftar Nominatif',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Perjadin => 'Surat Perjalanan Dinas yang dicetak per pelaksana, dua lembar bolak-balik.',
            self::RincianBiaya => 'Lampiran II PMK 113/PMK.05/2012 — rincian biaya beserta tanda tangannya.',
            self::DaftarRiil => 'Daftar pengeluaran riil transport lokal yang dinyatakan pelaksana.',
            self::DaftarNominatif => 'Daftar nominatif per surat tugas yang disahkan KPPN dan PPK.',
        };
    }

    /** Berkas tampilan yang dipakai dokumen ini. */
    public function view(): string
    {
        return match ($this) {
            self::Perjadin => 'spd.cetak',
            self::RincianBiaya => 'keuangan.cetak-rincian',
            self::DaftarRiil => 'daftar-riil.cetak',
            self::DaftarNominatif => 'laporan.cetak-nominatif',
        };
    }

    /** Daftar nominatif memanjang ke samping; selebihnya tegak. */
    public function orientasi(): string
    {
        return $this === self::DaftarNominatif ? KertasCetak::MENDATAR : KertasCetak::TEGAK;
    }

    public function orientasiLabel(): string
    {
        return $this->orientasi() === KertasCetak::MENDATAR ? 'Mendatar' : 'Tegak';
    }

    /** Dokumen berkop surat — yang lain memakai judul saja. */
    public function punyaKop(): bool
    {
        return in_array($this, [self::Perjadin, self::DaftarRiil], true);
    }

    public function hurufBawaan(): float
    {
        return match ($this) {
            self::Perjadin => 11,
            self::RincianBiaya => 10,
            self::DaftarRiil => 10.5,
            self::DaftarNominatif => 8,
        };
    }

    /**
     * Elemen yang boleh ditampilkan atau disembunyikan.
     *
     * @return array<string, array{label: string, keterangan: string, bawaan: bool}>
     */
    public function elemen(): array
    {
        return match ($this) {
            self::Perjadin => [
                'kop' => ['label' => 'Kop surat', 'keterangan' => 'Kop Poltekkes Kemenkes Manado di kepala tiap lembar.', 'bawaan' => true],
                'kode_nomor' => ['label' => 'Baris Kode Nomor', 'keterangan' => 'Akun pembebanan di atas nomor naskah.', 'bawaan' => true],
                'tingkat_biaya' => ['label' => 'Tingkat biaya', 'keterangan' => 'Butir 3c pada rincian pelaksana.', 'bawaan' => true],
                'pengikut' => ['label' => 'Tabel pengikut', 'keterangan' => 'Butir 8 — nama pengikut beserta keterangannya.', 'bawaan' => true],
                'keterangan_lain' => ['label' => 'Keterangan lain', 'keterangan' => 'Butir terakhir sebelum pengesahan.', 'bawaan' => true],
                'catatan_coret' => ['label' => 'Catatan "Coret yang tidak perlu"', 'keterangan' => 'Catatan kaki kecil di bawah tabel isi.', 'bawaan' => true],
                'kaki_gratifikasi' => ['label' => 'Pesan antigratifikasi', 'keterangan' => 'Pesan HALO KEMENKES beserta logo akreditasi di kaki halaman depan.', 'bawaan' => true],
            ],
            self::RincianBiaya => [
                'lampiran_pmk' => ['label' => 'Rujukan Lampiran II PMK', 'keterangan' => 'Blok rujukan peraturan di pojok kanan atas.', 'bawaan' => true],
                'rujukan' => ['label' => 'Blok rujukan SPPD', 'keterangan' => 'Nomor SPPD, tanggal, nama pelaksana, dan NIP.', 'bawaan' => true],
                'terbilang' => ['label' => 'Baris terbilang', 'keterangan' => 'Jumlah dalam kata di bawah tabel biaya.', 'bawaan' => true],
                'perhitungan_rampung' => ['label' => 'Perhitungan SPD rampung', 'keterangan' => 'Blok perhitungan di sisi kiri tanda tangan PPK.', 'bawaan' => true],
                'qr' => ['label' => 'QR tanda tangan', 'keterangan' => 'Kode QR dan kode konfirmasi pada tiap kolom tanda tangan.', 'bawaan' => true],
                'catatan_qr' => ['label' => 'Keterangan di bawah QR', 'keterangan' => 'Kalimat waktu penandatanganan dan ajakan memindai QR.', 'bawaan' => true],
            ],
            self::DaftarRiil => [
                'kop' => ['label' => 'Kop surat', 'keterangan' => 'Kop Poltekkes Kemenkes Manado di kepala halaman.', 'bawaan' => true],
                'nomor' => ['label' => 'Baris nomor', 'keterangan' => 'Nomor usulan dan dasar penugasan di bawah judul.', 'bawaan' => true],
                'pernyataan' => ['label' => 'Paragraf pernyataan', 'keterangan' => 'Pernyataan kesediaan menyetor kelebihan ke Kas Negara.', 'bawaan' => true],
                'identitas' => ['label' => 'Tabel identitas pelaksana', 'keterangan' => 'Nama, NIP, jabatan, unit kerja, tujuan, dan waktu pelaksanaan.', 'bawaan' => true],
                'qr' => ['label' => 'QR tanda tangan', 'keterangan' => 'Kode QR dan kode verifikasi pada kolom tanda tangan.', 'bawaan' => true],
                'catatan_qr' => ['label' => 'Keterangan di bawah QR', 'keterangan' => 'Kalimat waktu penandatanganan dan ajakan memindai QR.', 'bawaan' => true],
            ],
            self::DaftarNominatif => [
                'subjudul' => ['label' => 'Subjudul surat tugas', 'keterangan' => 'Baris nomor surat tugas di bawah judul.', 'bawaan' => true],
                'keterangan_pembiayaan' => ['label' => 'Kategori dan akun pembiayaan', 'keterangan' => 'Baris pembebanan anggaran di atas tabel.', 'bawaan' => true],
                'rentang_tanggal' => ['label' => 'Tanggal berangkat dan kembali', 'keterangan' => 'Dua baris tanggal di dalam kolom lamanya perjalanan.', 'bawaan' => true],
                'baris_total' => ['label' => 'Baris TOTAL', 'keterangan' => 'Penjumlahan seluruh pelaksana di kaki tabel.', 'bawaan' => true],
                'ttd' => ['label' => 'Blok tanda tangan', 'keterangan' => 'Kolom pengesahan KPPN dan Pejabat Pembuat Komitmen.', 'bawaan' => true],
                'qr' => ['label' => 'QR verifikasi PPK', 'keterangan' => 'Kode QR dan kode verifikasi pada kolom PPK.', 'bawaan' => true],
                'catatan_qr' => ['label' => 'Keterangan di bawah QR', 'keterangan' => 'Kalimat waktu penandatanganan dan ajakan memindai QR.', 'bawaan' => true],
            ],
        };
    }

    /**
     * Teks yang boleh diubah. Penanda :tahun dan :nomor diganti nilainya
     * saat dokumen dicetak.
     *
     * @return array<string, array{label: string, keterangan: string, bawaan: string, panjang: bool}>
     */
    public function teks(): array
    {
        return match ($this) {
            self::Perjadin => [
                'judul' => ['label' => 'Judul dokumen', 'keterangan' => 'Tercetak di tengah, di bawah nomor naskah.', 'bawaan' => 'SURAT PERJALANAN DINAS (SPD)', 'panjang' => false],
                'pesan_gratifikasi' => [
                    'label' => 'Pesan antigratifikasi',
                    'keterangan' => 'Tampil bila elemen pesan antigratifikasi dinyalakan.',
                    'bawaan' => 'Kementerian Kesehatan tidak menerima suap dan/atau gratifikasi dalam bentuk apapun. Jika terdapat potensi suap atau gratifikasi silakan laporkan melalui HALO KEMENKES 1500567 dan https://wbs.kemkes.go.id. Untuk verifikasi keaslian tanda tangan elektronik, silakan unggah dokumen pada laman https://tte.komdigi.go.id/verifyPDF.',
                    'panjang' => true,
                ],
            ],
            self::RincianBiaya => [
                'judul' => ['label' => 'Judul dokumen', 'keterangan' => 'Tercetak di tengah halaman.', 'bawaan' => 'Rincian Biaya Perjalanan Dinas', 'panjang' => false],
            ],
            self::DaftarRiil => [
                'judul' => ['label' => 'Judul dokumen', 'keterangan' => 'Tercetak di tengah, di bawah kop.', 'bawaan' => 'Daftar Pengeluaran Riil', 'panjang' => false],
                'pernyataan' => [
                    'label' => 'Isi paragraf pernyataan',
                    'keterangan' => 'Tampil bila elemen paragraf pernyataan dinyalakan.',
                    'bawaan' => 'Yang bertanda tangan di bawah ini menyatakan dengan sesungguhnya bahwa biaya perjalanan dinas di bawah ini benar-benar dikeluarkan untuk pelaksanaan perjalanan dinas dimaksud, dan apabila di kemudian hari terdapat kelebihan atas pembayaran tersebut, kami bersedia menyetorkan kelebihan tersebut ke Kas Negara.',
                    'panjang' => true,
                ],
            ],
            self::DaftarNominatif => [
                'judul' => ['label' => 'Judul dokumen', 'keterangan' => 'Penanda :tahun diganti tahun anggaran daftar ini.', 'bawaan' => 'NOMINATIF PERJADIN POLTEKKES KEMENKES MANADO TA :tahun', 'panjang' => false],
                'subjudul' => ['label' => 'Subjudul', 'keterangan' => 'Penanda :nomor diganti nomor surat tugas.', 'bawaan' => 'Surat Tugas No. :nomor', 'panjang' => false],
            ],
        };
    }

    /**
     * Pengaturan bawaan dokumen ini — dipakai bila belum pernah diubah.
     *
     * @return array{kertas: string, huruf: float, lebar_kop: int, elemen: array<string, bool>, teks: array<string, string>}
     */
    public function bawaan(): array
    {
        return [
            'kertas' => KertasCetak::UKURAN,
            'huruf' => $this->hurufBawaan(),
            'lebar_kop' => 82,
            'elemen' => array_map(fn (array $e) => $e['bawaan'], $this->elemen()),
            'teks' => array_map(fn (array $t) => $t['bawaan'], $this->teks()),
        ];
    }

    public static function dari(?string $nilai): ?self
    {
        return self::tryFrom((string) $nilai);
    }
}
