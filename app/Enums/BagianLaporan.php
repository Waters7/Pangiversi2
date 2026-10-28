<?php

namespace App\Enums;

/**
 * Bagian laporan perjalanan dinas format Kemenkes.
 *
 * Tiap bagian berisi uraian dan boleh dilengkapi satu foto dokumentasi,
 * yang tercetak pada halaman lampiran dokumen.
 */
enum BagianLaporan: string
{
    case Umum = 'umum';
    case MaksudTujuan = 'maksud-tujuan';
    case RuangLingkup = 'ruang-lingkup';
    case Dasar = 'dasar';
    case Kegiatan = 'kegiatan';
    case Hasil = 'hasil';
    case Simpulan = 'simpulan';
    case Penutup = 'penutup';

    /** Penomoran pada dokumen: A.1–A.4 lalu B–E. */
    public function nomor(): string
    {
        return match ($this) {
            self::Umum => 'A.1',
            self::MaksudTujuan => 'A.2',
            self::RuangLingkup => 'A.3',
            self::Dasar => 'A.4',
            self::Kegiatan => 'B',
            self::Hasil => 'C',
            self::Simpulan => 'D',
            self::Penutup => 'E',
        };
    }

    /** Nomor dan judul untuk rujukan: "A.1 Umum", "B. Kegiatan yang Dilaksanakan". */
    public function label(): string
    {
        return $this->nomor().($this->pendahuluan() ? ' ' : '. ').$this->judul();
    }

    public function judul(): string
    {
        return match ($this) {
            self::Umum => 'Umum',
            self::MaksudTujuan => 'Maksud dan Tujuan',
            self::RuangLingkup => 'Ruang Lingkup',
            self::Dasar => 'Dasar',
            self::Kegiatan => 'Kegiatan yang Dilaksanakan',
            self::Hasil => 'Hasil yang Dicapai',
            self::Simpulan => 'Simpulan dan Saran',
            self::Penutup => 'Penutup',
        };
    }

    /** Kolom isian pada tabel laporan_perjadin. */
    public function kolom(): string
    {
        return match ($this) {
            self::Umum => 'umum',
            self::MaksudTujuan => 'maksud_tujuan',
            self::RuangLingkup => 'ruang_lingkup',
            self::Dasar => 'dasar',
            self::Kegiatan => 'kegiatan_dilaksanakan',
            self::Hasil => 'hasil',
            self::Simpulan => 'kesimpulan',
            self::Penutup => 'penutup',
        };
    }

    /** Bagian Pendahuluan (A) bernomor 1–4 di bawah satu judul. */
    public function pendahuluan(): bool
    {
        return in_array($this, [self::Umum, self::MaksudTujuan, self::RuangLingkup, self::Dasar], true);
    }

    /** Bagian yang wajib diisi sebelum laporan dikirim ke pimpinan. */
    public function wajib(): bool
    {
        return in_array($this, [self::Kegiatan, self::Hasil, self::Simpulan], true);
    }

    public function petunjuk(): string
    {
        return match ($this) {
            self::Umum => 'Gambaran umum kegiatan dan latar belakangnya.',
            self::MaksudTujuan => 'Maksud dan tujuan perjalanan dinas ini.',
            self::RuangLingkup => 'Cakupan kegiatan: materi, peserta, dan batasannya.',
            self::Dasar => 'Dasar pelaksanaan, misalnya surat tugas dan undangan.',
            self::Kegiatan => 'Uraian kegiatan yang dilaksanakan selama perjalanan dinas.',
            self::Hasil => 'Hasil yang dicapai dari kegiatan tersebut.',
            self::Simpulan => 'Simpulan serta saran untuk satuan kerja.',
            self::Penutup => 'Kalimat penutup laporan.',
        };
    }
}
