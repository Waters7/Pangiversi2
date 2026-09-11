<?php

namespace App\Services;

use App\Enums\Kemampuan;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\LaporanPerjadin;
use App\Models\User;
use App\Models\Usulan;

/**
 * Berapa berkas yang menunggu tindakan seseorang, per menu.
 *
 * Dipakai lencana angka di sidebar. Sebelumnya satu-satunya cara mengetahui
 * ada pekerjaan menunggu adalah membuka menunya satu per satu — dan berkas
 * bisa mengendap berhari-hari hanya karena tidak ada yang tahu ia ada.
 *
 * Tiap angka lahir dari satu kueri hitung, dan hanya dihitung bila
 * penggunanya memang berhak membuka menu itu: sidebar dirender pada tiap
 * halaman, jadi yang tidak terpakai tidak boleh ikut membebani.
 */
class AntreanPeran
{
    /**
     * Seluruh angka antrean yang berlaku bagi pengguna ini.
     *
     * Kuncinya menamai menu, bukan kemampuannya, supaya Blade memanggil apa
     * yang dilihatnya di layar.
     *
     * @return array<string, int>
     */
    public function untuk(User $pengguna): array
    {
        return array_filter([
            'verifikasi-rincian' => $this->bila($pengguna, Kemampuan::MenandatanganiDaftarRiil, fn () => $this->rincianMenungguPpk()),
            'verifikasi-riil' => $this->bila($pengguna, Kemampuan::MenandatanganiDaftarRiil, fn () => $this->riilMenungguPpk()),
            'verifikasi-nominatif' => $this->bila($pengguna, Kemampuan::MenandatanganiDaftarRiil, fn () => $this->nominatifMenungguPpk()),
            'pembayaran' => $this->bila($pengguna, Kemampuan::MelihatPembayaran, fn () => $this->menungguDibayar()),
            'keuangan' => $this->bila($pengguna, Kemampuan::MemvalidasiBiaya, fn () => $this->menungguValidasi()),
            'laporan-pimpinan' => $this->bila($pengguna, Kemampuan::MengonfirmasiLaporanPerjadin, fn () => $this->laporanMenungguPimpinan()),
        ], fn (int $jumlah) => $jumlah > 0);
    }

    /**
     * Antrean yang sama, lengkap dengan sebutan dan tautannya, untuk
     * ditampilkan sebagai panel di halaman muka.
     *
     * Angkanya diambil dari untuk(), jadi memanggil keduanya dalam satu
     * halaman tidak menambah kueri apa pun.
     *
     * @return list<array{kunci: string, label: string, keterangan: string, tautan: string, jumlah: int}>
     */
    public function panel(User $pengguna): array
    {
        $sebutan = [
            'verifikasi-rincian' => [
                'label' => 'Rincian biaya menunggu tanda tangan',
                'keterangan' => 'Sudah disikapi pelaksana, tinggal disahkan.',
                'tautan' => 'persetujuan.rincian-biaya',
            ],
            'verifikasi-riil' => [
                'label' => 'Daftar riil menunggu tanda tangan',
                'keterangan' => 'Sudah disikapi pelaksana, tinggal disahkan.',
                'tautan' => 'persetujuan.daftar-riil',
            ],
            'verifikasi-nominatif' => [
                'label' => 'Daftar nominatif belum ditandatangani',
                'keterangan' => 'Terbit dan menunggu pengesahan PPK.',
                'tautan' => 'persetujuan.nominatif',
            ],
            'pembayaran' => [
                'label' => 'Perjadin menunggu dibayar',
                'keterangan' => 'Sudah berlaku, uang mukanya belum ditransfer.',
                'tautan' => 'pembayaran',
            ],
            'keuangan' => [
                'label' => 'Komponen biaya belum divalidasi',
                'keterangan' => 'Nominalnya belum dinyatakan benar.',
                'tautan' => 'keuangan',
            ],
            'laporan-pimpinan' => [
                'label' => 'Laporan perjadin menunggu konfirmasi',
                'keterangan' => 'Dikirim pelaksana, tinggal ditandatangani.',
                'tautan' => 'laporan-perjadin.index',
            ],
        ];

        return collect($this->untuk($pengguna))
            ->map(fn (int $jumlah, string $kunci) => [
                'kunci' => $kunci,
                'label' => $sebutan[$kunci]['label'],
                'keterangan' => $sebutan[$kunci]['keterangan'],
                'tautan' => route($sebutan[$kunci]['tautan']),
                'jumlah' => $jumlah,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  callable(): int  $hitung
     */
    private function bila(User $pengguna, Kemampuan $kemampuan, callable $hitung): int
    {
        return $pengguna->punyaKemampuan($kemampuan) ? $hitung() : 0;
    }

    /** Rincian biaya yang tinggal menunggu tanda tangan PPK. */
    public function rincianMenungguPpk(): int
    {
        return DaftarRiil::menungguPpk(JalurPersetujuan::RINCIAN)->count();
    }

    /** Daftar pengeluaran riil yang tinggal menunggu tanda tangan PPK. */
    public function riilMenungguPpk(): int
    {
        return DaftarRiil::menungguPpk(JalurPersetujuan::RIIL)->count();
    }

    /** Daftar nominatif yang sudah terbit tetapi belum ditandatangani. */
    public function nominatifMenungguPpk(): int
    {
        return DaftarNominatif::whereNull('ditandatangani_at')->count();
    }

    /**
     * Perjalanan yang sudah berlaku tetapi uang mukanya belum ditransfer —
     * tab bawaan menu Pembayaran.
     */
    public function menungguDibayar(): int
    {
        return Usulan::whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
            ->whereDoesntHave('keuangan', fn ($q) => $q->whereNotNull('tanggal_transfer'))
            ->count();
    }

    /** Laporan perjalanan dinas yang dikirim pelaksana dan belum diputuskan. */
    public function laporanMenungguPimpinan(): int
    {
        return LaporanPerjadin::menungguKonfirmasi()->count();
    }

    /** Berkas yang masih memuat komponen biaya belum divalidasi. */
    public function menungguValidasi(): int
    {
        return Usulan::whereIn('status', [StatusUsulan::Disetujui->value, StatusUsulan::Selesai->value])
            ->whereHas('keuangan.rincianBiaya', fn ($q) => $q->whereNull('divalidasi_at'))
            ->count();
    }
}
