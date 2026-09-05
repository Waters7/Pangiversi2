<?php

namespace App\Enums;

/**
 * Kemampuan (hak akses) yang dapat dimiliki sebuah peran.
 *
 * Nilai enum sekaligus menjadi nama Gate, sehingga otorisasi di route dan
 * controller cukup memakai `can:<nilai>` tanpa mendaftar ulang nama secara manual.
 */
enum Kemampuan: string
{
    case MengajukanUsulan = 'mengajukan-usulan';

    case MelihatSemuaUsulan = 'melihat-semua-usulan';

    case MelihatSemuaSpd = 'melihat-semua-spd';

    case MemvalidasiUsulan = 'memvalidasi-usulan';

    case MelihatKeuangan = 'melihat-keuangan';

    case MengelolaBiaya = 'mengelola-biaya';

    /**
     * Menyatakan nominal biaya sudah diperiksa dan benar.
     *
     * Dipisahkan dari MengelolaBiaya karena keduanya pekerjaan berbeda:
     * menyusun angka boleh dilakukan siapa pun yang mengelola biaya,
     * sedangkan menyatakannya benar adalah tugas tim keuangan. Bendahara
     * membayar berdasarkan angka yang sudah divalidasi, jadi ia tidak
     * memvalidasi pekerjaannya sendiri.
     */
    case MemvalidasiBiaya = 'memvalidasi-biaya';

    case MencatatPembayaran = 'mencatat-pembayaran';

    case MenandatanganiDaftarRiil = 'menandatangani-daftar-riil';

    case MelihatJadwalPerjalanan = 'melihat-jadwal-perjalanan';

    case MelihatDashboardEksekutif = 'melihat-dashboard-eksekutif';

    case MelihatLaporan = 'melihat-laporan';

    /**
     * Membaca arsip daftar riil dan daftar nominatif. Dipisahkan dari
     * MelihatLaporan agar Tim SDM dapat mengarsipkannya tanpa ikut terbuka
     * ke rekap anggaran dan master data.
     */
    case MelihatArsipPerjadin = 'melihat-arsip-perjadin';

    case MengelolaMasterData = 'mengelola-master-data';

    case MengelolaPengguna = 'mengelola-pengguna';

    case MelihatJejakAudit = 'melihat-jejak-audit';

    case MelihatPembayaran = 'melihat-pembayaran';

    public function label(): string
    {
        return match ($this) {
            self::MengajukanUsulan => 'Mengajukan perjalanan dinas',
            self::MelihatSemuaUsulan => 'Melihat seluruh usulan',
            self::MelihatSemuaSpd => 'Melihat seluruh Surat Perjalanan Dinas',
            self::MemvalidasiUsulan => 'Memvalidasi usulan',
            self::MelihatKeuangan => 'Melihat data keuangan',
            self::MengelolaBiaya => 'Menginput rincian biaya',
            self::MemvalidasiBiaya => 'Memvalidasi nominal biaya',
            self::MencatatPembayaran => 'Mencatat pembayaran dan bukti bayar',
            self::MelihatPembayaran => 'Membuka menu pembayaran bendahara',
            self::MenandatanganiDaftarRiil => 'Menandatangani daftar pengeluaran riil',
            self::MelihatJadwalPerjalanan => 'Melihat jadwal keberangkatan',
            self::MelihatDashboardEksekutif => 'Melihat dashboard eksekutif',
            self::MelihatLaporan => 'Melihat laporan dan rekap',
            self::MelihatArsipPerjadin => 'Melihat arsip daftar riil dan nominatif',
            self::MengelolaMasterData => 'Mengelola master data',
            self::MengelolaPengguna => 'Mengelola pengguna',
            self::MelihatJejakAudit => 'Melihat jejak audit',
        };
    }
}
