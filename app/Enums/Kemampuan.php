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

    /**
     * Menyunting tanggal terbit Surat Perjalanan Dinas.
     *
     * Tanggal ini biasanya mengikuti tanggal pembuatan dan tidak diutak-atik,
     * sebab ia menyatakan kapan surat benar-benar terbit. Hanya pimpinan dan
     * administrator yang boleh menyesuaikannya, misalnya ketika nomor surat
     * sudah tercatat di buku agenda pada tanggal yang berbeda.
     */
    case MengubahTanggalSpd = 'mengubah-tanggal-spd';

    /**
     * Mengisi kolom pengikut pada Surat Perjalanan Dinas.
     *
     * Pengikut ikut berangkat tanpa menjadi pelaksana dan tidak mengajukan
     * usulan sendiri, sehingga pencantumannya menjadi keputusan pimpinan —
     * bukan pilihan pengusul.
     */
    case MengisiPengikutSpd = 'mengisi-pengikut-spd';

    /**
     * Mengonfirmasi dan menandatangani laporan perjalanan dinas, atau
     * mengembalikannya kepada pelaksana untuk direvisi. Konfirmasi inilah
     * salah satu syarat pelunasan pembayaran.
     */
    case MengonfirmasiLaporanPerjadin = 'mengonfirmasi-laporan-perjadin';

    /**
     * Menghapus baris master data. Dipisahkan dari MengelolaMasterData supaya
     * sebuah peran dapat diberi hak menambah dan menyunting referensi tanpa
     * hak menghapusnya.
     */
    case MenghapusMasterData = 'menghapus-master-data';

    /** Menghapus akun pengguna — dipisahkan dari MengelolaPengguna dengan alasan yang sama. */
    case MenghapusPengguna = 'menghapus-pengguna';

    /**
     * Mengelola peran dan hak aksesnya: menambah peran baru serta mengatur
     * menu mana yang boleh dilihat, diubah, dan dihapus tiap peran.
     */
    case MengelolaPeran = 'mengelola-peran';

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
            self::MengubahTanggalSpd => 'Mengubah tanggal terbit SPD',
            self::MengisiPengikutSpd => 'Mengisi pengikut pada SPD',
            self::MengonfirmasiLaporanPerjadin => 'Mengonfirmasi laporan perjalanan dinas',
            self::MenandatanganiDaftarRiil => 'Menandatangani daftar pengeluaran riil',
            self::MelihatJadwalPerjalanan => 'Melihat jadwal keberangkatan',
            self::MelihatDashboardEksekutif => 'Melihat dashboard eksekutif',
            self::MelihatLaporan => 'Melihat laporan dan rekap',
            self::MelihatArsipPerjadin => 'Melihat arsip daftar riil dan nominatif',
            self::MengelolaMasterData => 'Mengelola master data',
            self::MengelolaPengguna => 'Mengelola pengguna',
            self::MelihatJejakAudit => 'Melihat jejak audit',
            self::MenghapusMasterData => 'Menghapus master data',
            self::MenghapusPengguna => 'Menghapus pengguna',
            self::MengelolaPeran => 'Mengelola peran dan hak akses',
        };
    }

    /**
     * Sifat kemampuan ini pada menunya — kolom tempatnya berada pada matriks
     * hak akses.
     */
    public function jenis(): JenisAkses
    {
        return match ($this) {
            self::MelihatSemuaUsulan,
            self::MelihatSemuaSpd,
            self::MelihatKeuangan,
            self::MelihatPembayaran,
            self::MelihatJadwalPerjalanan,
            self::MelihatDashboardEksekutif,
            self::MelihatLaporan,
            self::MelihatArsipPerjadin,
            self::MelihatJejakAudit => JenisAkses::Lihat,

            self::MenghapusMasterData,
            self::MenghapusPengguna => JenisAkses::Hapus,

            default => JenisAkses::Ubah,
        };
    }
}
