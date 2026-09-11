<?php

namespace App\Http\Controllers;

use App\Enums\Kemampuan;
use App\Models\DaftarRiil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panduan penggunaan PANGI, disusun mengikuti urutan yang benar-benar
 * dilalui pengguna: menerbitkan SPD, mengajukan, berangkat, lalu
 * mempertanggungjawabkan.
 *
 * Bagiannya menyesuaikan kewenangan yang membuka. Seluruh peran dapat
 * bepergian, jadi bagian pelaksana selalu tampil; bagian PPK, tim keuangan,
 * bendahara, dan administrator hanya muncul bagi yang memang mengerjakannya
 * — panduan yang memuat pekerjaan orang lain hanya menyulitkan pembacanya
 * menemukan bagiannya sendiri.
 */
class PanduanController extends Controller
{
    /**
     * Buku panduan lengkap yang ikut dikemas bersama aplikasi.
     *
     * Disimpan di resources/ dan ikut versi kode, supaya buku yang diunduh
     * selalu sepasang dengan sistem yang sedang berjalan — bukan berkas lepas
     * di storage yang bisa tertinggal saat aplikasi diperbarui.
     */
    public const BERKAS_BUKU = 'Buku-Panduan-PANGI-v2.4.docx';

    public function __invoke(Request $request): View
    {
        $pengguna = $request->user();

        return view('panduan.index', [
            'peran' => $pengguna->peran,
            'bagian' => $this->bagian($pengguna),
            'tugasPeran' => $this->tugasPeran($pengguna),
            // Tombol unduh disembunyikan bila bukunya memang tidak terpasang,
            // supaya tidak menjanjikan berkas yang berujung halaman 404.
            'bukuTersedia' => is_file($this->jalurBuku()),
            'namaBuku' => self::BERKAS_BUKU,
            // Sebagian bagian memuat langkah yang tidak berlaku bagi seluruh
            // pembacanya — Tim SDM mengelola pengguna tanpa menyentuh master
            // data maupun jejak audit.
            'boleh' => [
                'masterData' => $pengguna->punyaKemampuan(Kemampuan::MengelolaMasterData),
                'jejakAudit' => $pengguna->punyaKemampuan(Kemampuan::MelihatJejakAudit),
            ],
            'hariSanggah' => DaftarRiil::HARI_MASA_SANGGAH,
        ]);
    }

    /**
     * Unduh buku panduan lengkap dalam format Word.
     *
     * Yang di layar menyesuaikan peran pembacanya; berkas ini memuat
     * seluruhnya sekaligus, untuk dicetak atau dibagikan di luar aplikasi.
     */
    public function unduh(): BinaryFileResponse
    {
        abort_unless(
            is_file($this->jalurBuku()),
            404,
            'Buku panduan belum terpasang pada aplikasi ini.',
        );

        return response()->download($this->jalurBuku(), self::BERKAS_BUKU);
    }

    private function jalurBuku(): string
    {
        return resource_path('panduan/'.self::BERKAS_BUKU);
    }

    /**
     * Bagian panduan yang berlaku bagi pengguna ini, berurutan menurut alur
     * kerjanya.
     *
     * @return array<string, string>
     */
    private function bagian(User $pengguna): array
    {
        $bagian = [
            'spd' => 'Menerbitkan SPD',
            'pengajuan' => 'Mengajukan Perjadin',
            'berkas' => 'Berkas & Laporan',
            'rincian' => 'Memeriksa & Menandatangani',
        ];

        $tambahan = [
            'keuangan' => [Kemampuan::MemvalidasiBiaya, 'Menyusun Rincian Biaya'],
            'ppk' => [Kemampuan::MenandatanganiDaftarRiil, 'Tanda Tangan PPK'],
            'bendahara' => [Kemampuan::MencatatPembayaran, 'Mencatat Pembayaran'],
            'laporan-pimpinan' => [Kemampuan::MengonfirmasiLaporanPerjadin, 'Mengonfirmasi Laporan Perjadin'],
            'pemantauan' => [Kemampuan::MelihatLaporan, 'Memantau & Melaporkan'],
            'administrasi' => [Kemampuan::MengelolaPengguna, 'Mengelola Pengguna'],
        ];

        foreach ($tambahan as $kunci => [$kemampuan, $label]) {
            if ($pengguna->punyaKemampuan($kemampuan)) {
                $bagian[$kunci] = $label;
            }
        }

        $bagian['bantuan'] = 'Melapor Kendala';

        return $bagian;
    }

    /**
     * Pekerjaan yang menjadi tanggung jawab peran ini di luar bepergian
     * sendiri, beserta bagian panduan yang menjelaskannya.
     *
     * @return list<array{tugas: string, bagian: string}>
     */
    private function tugasPeran(User $pengguna): array
    {
        $tugas = [
            [Kemampuan::MemvalidasiBiaya, 'Menyusun rincian biaya dan menyatakan nominalnya benar', 'keuangan'],
            [Kemampuan::MenandatanganiDaftarRiil, 'Menandatangani rincian biaya, daftar riil, dan daftar nominatif', 'ppk'],
            [Kemampuan::MencatatPembayaran, 'Mencairkan uang muka, pelunasan, dan penggantian transport lokal', 'bendahara'],
            [Kemampuan::MengonfirmasiLaporanPerjadin, 'Mengonfirmasi dan menandatangani laporan perjalanan dinas, atau mengembalikannya untuk direvisi', 'laporan-pimpinan'],
            [Kemampuan::MelihatLaporan, 'Memantau realisasi anggaran dan menarik rekap perjalanan dinas', 'pemantauan'],
            [Kemampuan::MengelolaPengguna, 'Mengelola akun pengguna dan penempatannya', 'administrasi'],
            [Kemampuan::MengelolaMasterData, 'Mengelola master data: kategori, komponen biaya, dan tujuan', 'administrasi'],
        ];

        return collect($tugas)
            ->filter(fn (array $baris) => $pengguna->punyaKemampuan($baris[0]))
            ->map(fn (array $baris) => ['tugas' => $baris[1], 'bagian' => $baris[2]])
            ->values()
            ->all();
    }
}
