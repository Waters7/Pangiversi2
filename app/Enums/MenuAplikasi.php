<?php

namespace App\Enums;

/**
 * Menu aplikasi sebagaimana tampil di sidebar, beserta kemampuan yang
 * mengaturnya. Menjadi baris pada matriks hak akses peran: tiap kemampuan
 * ditempatkan pada kolom Lihat, Ubah, atau Hapus menurut sifatnya.
 */
enum MenuAplikasi: string
{
    case PerjalananDinasSendiri = 'perjalanan-dinas-sendiri';

    case DashboardEksekutif = 'dashboard-eksekutif';

    case BuatSpd = 'buat-spd';

    case UsulanPerjadin = 'usulan-perjadin';

    case LaporanPerjadin = 'laporan-perjadin';

    case Persetujuan = 'persetujuan';

    case JadwalPerjalanan = 'jadwal-perjalanan';

    case Pembayaran = 'pembayaran';

    case Keuangan = 'keuangan';

    case LaporanArsip = 'laporan-arsip';

    case MasterData = 'master-data';

    case JejakAudit = 'jejak-audit';

    case AdministrasiSistem = 'administrasi-sistem';

    public function label(): string
    {
        return match ($this) {
            self::PerjalananDinasSendiri => 'Perjalanan dinas sendiri',
            self::DashboardEksekutif => 'Dashboard Eksekutif',
            self::BuatSpd => 'Buat SPD',
            self::UsulanPerjadin => 'Usulan Perjadin',
            self::LaporanPerjadin => 'Laporan Perjadin (pimpinan)',
            self::Persetujuan => 'Persetujuan (PPK)',
            self::JadwalPerjalanan => 'Jadwal Perjalanan',
            self::Pembayaran => 'Pembayaran',
            self::Keuangan => 'Keuangan',
            self::LaporanArsip => 'Laporan & Arsip',
            self::MasterData => 'Master Data',
            self::JejakAudit => 'Jejak Audit',
            self::AdministrasiSistem => 'Administrasi Sistem',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::PerjalananDinasSendiri => 'Buat SPD, Usulan Perjadin, Dokumen, dan Rincian Saya atas nama sendiri — dasar bagi setiap peran.',
            self::DashboardEksekutif => 'Ringkasan anggaran, Wawasan AI, dan agen tanya-jawab.',
            self::BuatSpd => 'Kewenangan tambahan atas SPD milik orang lain.',
            self::UsulanPerjadin => 'Kewenangan tambahan atas usulan milik orang lain.',
            self::LaporanPerjadin => 'Mengonfirmasi, menandatangani, atau mengembalikan laporan perjalanan dinas.',
            self::Persetujuan => 'Menandatangani rincian biaya, daftar riil, dan daftar nominatif.',
            self::JadwalPerjalanan => 'Siapa saja yang berangkat dan kapan.',
            self::Pembayaran => 'Uang muka, pelunasan, transport lokal, dan bukti bayar.',
            self::Keuangan => 'Rincian biaya per usulan beserta validasinya.',
            self::LaporanArsip => 'Rekap anggaran, arsip daftar riil, rincian lengkap, dan daftar nominatif.',
            self::MasterData => 'Unit kerja, lokasi, kategori, komponen biaya, tahun anggaran, dan jenis kegiatan.',
            self::JejakAudit => 'Riwayat setiap tindakan pengguna.',
            self::AdministrasiSistem => 'Akun pengguna, impor & ekspor, pengaturan sistem, serta peran dan hak akses.',
        };
    }

    /**
     * Kemampuan yang mengatur menu ini.
     *
     * @return list<Kemampuan>
     */
    public function kemampuan(): array
    {
        return match ($this) {
            self::PerjalananDinasSendiri => [Kemampuan::MengajukanUsulan],
            self::DashboardEksekutif => [Kemampuan::MelihatDashboardEksekutif],
            self::BuatSpd => [Kemampuan::MelihatSemuaSpd, Kemampuan::MengubahTanggalSpd, Kemampuan::MengisiPengikutSpd],
            self::UsulanPerjadin => [Kemampuan::MelihatSemuaUsulan, Kemampuan::MemvalidasiUsulan],
            self::LaporanPerjadin => [Kemampuan::MengonfirmasiLaporanPerjadin],
            self::Persetujuan => [Kemampuan::MenandatanganiDaftarRiil],
            self::JadwalPerjalanan => [Kemampuan::MelihatJadwalPerjalanan],
            self::Pembayaran => [Kemampuan::MelihatPembayaran, Kemampuan::MencatatPembayaran],
            self::Keuangan => [Kemampuan::MelihatKeuangan, Kemampuan::MengelolaBiaya, Kemampuan::MemvalidasiBiaya],
            self::LaporanArsip => [Kemampuan::MelihatArsipPerjadin, Kemampuan::MelihatLaporan],
            self::MasterData => [Kemampuan::MengelolaMasterData, Kemampuan::MenghapusMasterData],
            self::JejakAudit => [Kemampuan::MelihatJejakAudit],
            self::AdministrasiSistem => [Kemampuan::MengelolaPengguna, Kemampuan::MenghapusPengguna, Kemampuan::MengelolaPeran],
        };
    }

    /**
     * Kemampuan menu ini yang bersifat tertentu — isi satu sel matriks.
     *
     * @return list<Kemampuan>
     */
    public function kemampuanJenis(JenisAkses $jenis): array
    {
        return array_values(array_filter($this->kemampuan(), fn (Kemampuan $k) => $k->jenis() === $jenis));
    }

    /**
     * Setiap kemampuan harus punya tepat satu menu, supaya tidak ada yang
     * luput dari matriks. Dipakai uji otomatis.
     *
     * @return list<Kemampuan>
     */
    public static function seluruhKemampuan(): array
    {
        return array_merge(...array_map(fn (self $menu) => $menu->kemampuan(), self::cases()));
    }
}
