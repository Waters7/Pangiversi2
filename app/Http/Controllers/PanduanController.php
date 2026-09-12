<?php

namespace App\Http\Controllers;

use App\Enums\Kemampuan;
use App\Models\DaftarRiil;
use App\Models\User;
use App\Services\VersiAplikasi;
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
    public const BERKAS_BUKU = 'Buku-Panduan-PANGI-v2.5.docx';

    public function __invoke(Request $request, VersiAplikasi $versi): View
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
            'versi' => $versi->label(),
            'riwayat' => $this->riwayatPerubahan(),
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
        $bagian['perubahan'] = 'Riwayat Perubahan';

        return $bagian;
    }

    /**
     * Yang berubah dari versi ke versi — terbaru di atas — supaya pembaca
     * versi lama tahu bagian mana yang perlu dibaca ulang. Sengaja ditulis
     * dari sudut pengguna, bukan daftar commit.
     *
     * @return list<array{versi: string, tanggal: string, butir: list<string>}>
     */
    private function riwayatPerubahan(): array
    {
        return [
            [
                'versi' => '2.5',
                'tanggal' => '12 September 2026',
                'butir' => [
                    'Usulan wajib melampirkan SPD bertanda tangan beserta nomornya; persetujuan PPK tercatat otomatis dari SPD itu. Pengajuan berkelompok dan tombol Salin dihapus — satu orang satu usulan.',
                    'Laporan perjalanan dinas: tempat kegiatan per hari, tersimpan sekaligus terkirim ke pimpinan, dikonfirmasi dan ditandatangani Direktur lewat QR, atau dikembalikan untuk direvisi. Menu Laporan Perjadin bagi pimpinan.',
                    'Pelunasan hanya menunggu konfirmasi laporan oleh Direktur — bukan tanda tangan PPK maupun daftar nominatif.',
                    'Daftar nominatif terbit per surat tugas begitu satu pelaksana tuntas; yang belum tercantum disebut dan bertambah sendiri. List Daftar Nominatif memuat seluruh daftar beserta status tanda tangan tiap pelaksana; cetakannya memuat QR PPK.',
                    'Berkas terkirim otomatis ke pelaksana begitu seluruh validasi rampung, berstatus Sudah Dicek Tim Keuangan; tombol tanda tangan pelaksana tetap ada setelah masa sanggah; validasi dan pencabutannya dikonfirmasi lewat popup.',
                    'Biaya penyelenggaraan: ditanya ada atau tidak; bila ada, nominal, bukti bayar, dan nomor invoice, tercetak di bawah uang penginapan.',
                    'Tiket membawa invoice; nota transport lokal hanya untuk ruas bernominal (transport lokal boleh tidak ada); dalam kota satu baris transport lokal; checklist kelengkapan mengikuti formulir.',
                    'Verifikasi PPK dan Arsip Rincian Lengkap dikelompokkan per bulan dan tahun; arsip tidak lagi menunggu nominatif; menu Keuangan bagi PPK dalam mode lihat saja.',
                    'Pembatalan penggantian transport lokal dengan alasan, tercatat pada riwayat.',
                    'Seluruh dokumen cetak berukuran folio dan dirapikan: usulan sebagai dokumen resmi berkop, rincian satu halaman, tanggal berbahasa Indonesia, WITA.',
                    'Pelacakan berkas: sepuluh tonggak dengan lama hari antar tahap; versi aplikasi tertera di halaman masuk.',
                ],
            ],
            [
                'versi' => '2.4',
                'tanggal' => '5 September 2026',
                'butir' => [
                    'Rilis dasar versi 2: Surat Perjalanan Dinas dengan nomor unik dan pengikut, pertanggungjawaban per pelaksana (tiket, nota, bill hotel, laporan), rincian biaya dan daftar riil dengan dua jalur tanda tangan, daftar nominatif, pembayaran beserta riwayatnya, pelacakan berkas, dan saluran bantuan.',
                ],
            ],
        ];
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
