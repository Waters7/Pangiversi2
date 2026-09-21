<?php

namespace App\Enums;

/**
 * Peran pengguna PANGI beserta hak aksesnya.
 *
 * Seluruh peran berhak mengajukan perjalanan dinas; yang membedakan adalah
 * kewenangan validasi, keuangan, dan administrasi di atasnya.
 */
enum PeranPengguna: string
{
    case SuperAdministrator = 'super_administrator';

    case Pimpinan = 'pimpinan';

    case Ppk = 'ppk';

    case Bendahara = 'bendahara';

    case TimKeuangan = 'tim_keuangan';

    case TimSdm = 'tim_sdm';

    case DosenTendik = 'dosen_tendik';

    case PegawaiEksternal = 'pegawai_eksternal';

    case Outsourcing = 'outsourcing';

    case Mahasiswa = 'mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Super Administrator',
            self::Pimpinan => 'Pimpinan (Direktur / Wadir)',
            self::Ppk => 'PPK',
            self::Bendahara => 'Bendahara',
            self::TimKeuangan => 'Tim Keuangan',
            self::TimSdm => 'Tim SDM',
            self::DosenTendik => 'Dosen / Tendik Internal',
            self::PegawaiEksternal => 'Pegawai Kemenkes Eksternal',
            self::Outsourcing => 'Outsourcing',
            self::Mahasiswa => 'Mahasiswa',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Seluruh hak akses sistem',
            self::Pimpinan => 'Melihat seluruh data dan memberi validasi akhir',
            self::Ppk => 'Validasi perjalanan dinas dan tanda tangan daftar pengeluaran riil',
            self::Bendahara => 'Modul keuangan beserta pencatatan bukti pembayaran',
            self::TimKeuangan => 'Input rincian biaya dan modul keuangan selain bukti bayar',
            self::TimSdm => 'Melihat daftar pegawai yang disetujui berangkat, tanpa rincian',
            self::DosenTendik => 'Pengusul perjalanan dinas',
            self::PegawaiEksternal => 'Pengusul perjalanan dinas',
            self::Outsourcing => 'Pengusul perjalanan dinas',
            self::Mahasiswa => 'Pengusul perjalanan dinas',
        };
    }

    /**
     * Modul untuk peran ini sudah dapat dipakai.
     *
     * Pegawai eksternal, mahasiswa, dan outsourcing akan dilayani modul
     * tersendiri yang masih dikembangkan; sampai siap, setelah masuk mereka
     * hanya melihat halaman pemberitahuan.
     */
    public function modulTersedia(): bool
    {
        return ! in_array($this, [self::PegawaiEksternal, self::Mahasiswa, self::Outsourcing], true);
    }

    /**
     * Peran yang modulnya sudah tersedia — yang benar-benar memakai aplikasi.
     *
     * @return list<self>
     */
    public static function bermodul(): array
    {
        return array_values(array_filter(self::cases(), fn (self $peran) => $peran->modulTersedia()));
    }

    /**
     * Hak akses yang melekat pada peran ini.
     *
     * @return list<Kemampuan>
     */
    public function kemampuan(): array
    {
        // Semua peran dapat mengajukan perjalanan dinas.
        $dasar = [Kemampuan::MengajukanUsulan];

        return match ($this) {
            self::SuperAdministrator => Kemampuan::cases(),

            // Pimpinan memantau seluruh proses tanpa ikut menjadi tahap validasi.
            self::Pimpinan => [
                ...$dasar,
                Kemampuan::MelihatSemuaUsulan,
                Kemampuan::MelihatKeuangan,
                Kemampuan::MelihatJadwalPerjalanan,
                Kemampuan::MelihatDashboardEksekutif,
                Kemampuan::MelihatLaporan,
                Kemampuan::MelihatArsipPerjadin,
                // Menyesuaikan tanggal terbit SPD dengan buku agenda, dan
                // memutuskan siapa yang ikut berangkat sebagai pengikut.
                Kemampuan::MengubahTanggalSpd,
                Kemampuan::MengisiPengikutSpd,
                // Laporan perjalanan dinas berakhir di meja pimpinan: dikonfirmasi
                // dan ditandatangani, atau dikembalikan untuk direvisi.
                Kemampuan::MengonfirmasiLaporanPerjadin,
            ],

            // Satu-satunya peran yang memvalidasi usulan perjalanan dinas.
            self::Ppk => [
                ...$dasar,
                Kemampuan::MelihatSemuaUsulan,
                Kemampuan::MemvalidasiUsulan,
                Kemampuan::MelihatKeuangan,
                Kemampuan::MenandatanganiDaftarRiil,
                Kemampuan::MelihatJadwalPerjalanan,
                Kemampuan::MelihatDashboardEksekutif,
                Kemampuan::MelihatLaporan,
                Kemampuan::MelihatArsipPerjadin,
            ],

            // Bendahara membayar berdasarkan angka yang sudah divalidasi tim
            // keuangan, jadi ia sengaja tidak ikut memvalidasi.
            self::Bendahara => [
                ...$dasar,
                Kemampuan::MelihatKeuangan,
                Kemampuan::MengelolaBiaya,
                Kemampuan::MencatatPembayaran,
                // Menu Pembayaran memang khusus bendahara.
                Kemampuan::MelihatPembayaran,
                Kemampuan::MelihatJadwalPerjalanan,
                Kemampuan::MelihatDashboardEksekutif,
                Kemampuan::MelihatLaporan,
                Kemampuan::MelihatArsipPerjadin,
            ],

            // Tim keuangan menangani seluruh modul keuangan kecuali bukti bayar,
            // dan merekalah yang menyatakan nominalnya sudah benar.
            self::TimKeuangan => [
                ...$dasar,
                Kemampuan::MelihatKeuangan,
                Kemampuan::MengelolaBiaya,
                Kemampuan::MemvalidasiBiaya,
                Kemampuan::MelihatJadwalPerjalanan,
                Kemampuan::MelihatDashboardEksekutif,
                Kemampuan::MelihatLaporan,
                Kemampuan::MelihatArsipPerjadin,
            ],

            // Tim SDM melihat siapa saja yang akan berangkat dan mengelola akun
            // pengguna, tetapi tidak menyentuh biaya maupun validasi. Seluruh SPD
            // terbuka baginya karena ia yang mengarsipkan surat penugasan.
            self::TimSdm => [
                ...$dasar,
                Kemampuan::MelihatJadwalPerjalanan,
                Kemampuan::MelihatSemuaSpd,
                Kemampuan::MengelolaPengguna,
                Kemampuan::MenghapusPengguna,
                Kemampuan::MelihatArsipPerjadin,
            ],

            self::DosenTendik, self::PegawaiEksternal, self::Mahasiswa, self::Outsourcing => $dasar,
        };
    }

    public function punya(Kemampuan $kemampuan): bool
    {
        return in_array($kemampuan, $this->kemampuan(), true);
    }

    /**
     * Peran yang tidak memiliki kewenangan di luar mengajukan usulan.
     */
    public function pengusulBiasa(): bool
    {
        return in_array($this, [self::DosenTendik, self::PegawaiEksternal, self::Mahasiswa, self::Outsourcing], true);
    }

    /**
     * Daftar peran untuk dropdown administrasi pengguna.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $peran) {
            $options[$peran->value] = $peran->label();
        }

        return $options;
    }

    /**
     * Terjemahkan nilai peran apa pun menjadi enum, termasuk nama peran lama
     * yang belum sempat dimigrasikan.
     */
    public static function dari(?string $nilai): self
    {
        return match ($nilai) {
            'administrator' => self::SuperAdministrator,
            'direktur' => self::Pimpinan,
            'keuangan' => self::Bendahara,
            'sdm' => self::TimSdm,
            'pegawai' => self::DosenTendik,
            default => self::tryFrom((string) $nilai) ?? self::DosenTendik,
        };
    }
}
