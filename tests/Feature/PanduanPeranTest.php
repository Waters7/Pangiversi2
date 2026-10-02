<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Http\Controllers\PanduanController;
use App\Models\DaftarRiil;
use App\Models\User;
use App\Services\VersiAplikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Panduan penggunaan menyesuaikan kewenangan yang membukanya.
 *
 * Seluruh peran dapat bepergian, jadi bagian pelaksana selalu ada. Yang
 * berbeda adalah bagian di atasnya: panduan yang memuat pekerjaan orang lain
 * hanya menyulitkan pembacanya menemukan bagiannya sendiri, dan panduan yang
 * menghilangkan pekerjaannya sendiri membuatnya menebak.
 */
class PanduanPeranTest extends TestCase
{
    use RefreshDatabase;

    private function bukaSebagai(PeranPengguna $peran): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => $peran->value]))
            ->get(route('panduan'))
            ->assertOk();
    }

    // ── Bagian yang berlaku bagi semua ──

    /**
     * Peran yang sudah dapat membuka aplikasi; yang modulnya masih
     * dikembangkan ditahan pada halaman pemberitahuan, belum sampai ke panduan.
     *
     * @return list<array{0: PeranPengguna, 1: string}>
     */
    public static function peran(): array
    {
        return array_map(
            fn (PeranPengguna $peran) => [$peran, $peran->value],
            PeranPengguna::bermodul(),
        );
    }

    #[DataProvider('peran')]
    public function test_bagian_pelaksana_terbuka_bagi_seluruh_peran(PeranPengguna $peran, string $nama): void
    {
        $halaman = $this->bukaSebagai($peran);

        // Seluruh peran berhak mengajukan perjalanan dinas, jadi keempat
        // bagian ini tidak boleh hilang dari siapa pun.
        foreach ([
            'Menerbitkan SPD',
            'Mengajukan Perjadin',
            'Berkas &amp; Laporan',
            'Memeriksa &amp; Menandatangani',
        ] as $bagian) {
            $halaman->assertSee($bagian, escape: false);
        }
    }

    #[DataProvider('peran')]
    public function test_panduan_menyebut_peran_yang_membukanya(PeranPengguna $peran, string $nama): void
    {
        $this->bukaSebagai($peran)
            ->assertSee('Anda masuk sebagai')
            ->assertSee($peran->label());
    }

    // ── Bagian yang hanya muncul bagi yang mengerjakannya ──

    public function test_ppk_melihat_bagian_tanda_tangan(): void
    {
        $this->bukaSebagai(PeranPengguna::Ppk)
            ->assertSee('Tanda Tangan PPK')
            ->assertSee('Verifikasi Daftar Nominatif')
            ->assertDontSee('Mencatat Pembayaran');
    }

    public function test_pimpinan_melihat_bagian_konfirmasi_laporan(): void
    {
        $this->bukaSebagai(PeranPengguna::Pimpinan)
            ->assertSee('Mengonfirmasi Laporan Perjadin')
            ->assertSee('Kembalikan untuk Revisi');
    }

    public function test_pelaksana_tidak_melihat_bagian_konfirmasi_laporan(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertDontSee('Mengonfirmasi Laporan Perjadin')
            // Tetapi tahu laporannya harus dikirim ke pimpinan.
            ->assertSee('Simpan dan kirim ke pimpinan');
    }

    public function test_bendahara_melihat_bagian_pembayaran(): void
    {
        $this->bukaSebagai(PeranPengguna::Bendahara)
            ->assertSee('Mencatat Pembayaran')
            ->assertSee('Bayar Transport Lokal')
            // Bendahara memang tidak memvalidasi biaya.
            ->assertDontSee('Menyusun Rincian Biaya')
            ->assertDontSee('Tanda Tangan PPK');
    }

    public function test_tim_keuangan_melihat_bagian_penyusunan_biaya(): void
    {
        $this->bukaSebagai(PeranPengguna::TimKeuangan)
            ->assertSee('Menyusun Rincian Biaya')
            ->assertSee('Periksa Transport Lokal')
            ->assertDontSee('Mencatat Pembayaran');
    }

    public function test_pelaksana_biasa_hanya_melihat_bagian_pelaksana(): void
    {
        $halaman = $this->bukaSebagai(PeranPengguna::DosenTendik);

        foreach ([
            'Tanda Tangan PPK',
            'Menyusun Rincian Biaya',
            'Mencatat Pembayaran',
            'Memantau &amp; Melaporkan',
            'Mengelola Pengguna',
        ] as $bukanUrusannya) {
            $halaman->assertDontSee($bukanUrusannya, escape: false);
        }
    }

    public function test_administrator_melihat_seluruh_bagian(): void
    {
        $halaman = $this->bukaSebagai(PeranPengguna::SuperAdministrator);

        foreach ([
            'Menyusun Rincian Biaya',
            'Tanda Tangan PPK',
            'Mencatat Pembayaran',
            'Memantau &amp; Melaporkan',
            'Mengelola Pengguna',
        ] as $bagian) {
            $halaman->assertSee($bagian, escape: false);
        }
    }

    // ── Ringkasan tugas peran ──

    public function test_pelaksana_biasa_tidak_diberi_daftar_tugas_tambahan(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSee('Panduan ini memuat seluruh yang perlu Anda lakukan')
            ->assertDontSee('peran Anda menangani');
    }

    public function test_peran_berwenang_diberi_ringkasan_tugasnya(): void
    {
        $this->bukaSebagai(PeranPengguna::Bendahara)
            ->assertSee('peran Anda menangani')
            ->assertSee('Mencairkan uang muka, pelunasan, dan penggantian transport lokal');
    }

    // ── Isi yang harus sesuai sistem berjalan ──

    /** Masa sanggah dibaca dari sumbernya, bukan ditulis tetap di panduan. */
    public function test_masa_sanggah_mengikuti_nilai_yang_berlaku(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSee('Masa sanggah '.DaftarRiil::HARI_MASA_SANGGAH.' hari');
    }

    /** Berkas yang ditagih bercabang menurut wilayah perjalanannya. */
    public function test_panduan_berkas_memisahkan_dalam_kota_dan_luar_kota(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSee('Dalam kota')
            ->assertSee('Luar kota')
            ->assertSee('Bill hotel beserta nomor transaksi &amp; nominal', false)
            ->assertSee('Bukti bayar biaya penyelenggaraan');
    }

    /** Riwayat perubahan tampil bagi semua peran, menyebut versi tayang dan rilis terbaru. */
    public function test_panduan_memuat_riwayat_perubahan(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSee('Riwayat Perubahan')
            ->assertSee('Versi 2.5.11')
            ->assertSee('unduh SPD bertanda tangan beserta nomor naskahnya')
            ->assertSee('Versi 2.5.10')
            ->assertSee('Nomor Surat')
            ->assertSee('Versi 2.5.9')
            ->assertSee('Kirim Ulang ke Pelaksana')
            ->assertSee('Versi 2.5.8')
            ->assertSee('Panduan bergambar')
            ->assertSee('Versi 2.5.6')
            ->assertSee('Berkas Pertanggungjawaban')
            ->assertSee('Supervisi Kerja Praktek')
            ->assertSee('Dokumen Output')
            ->assertSee('surat tugas dilampirkan sejak SPD dibuat')
            ->assertSee('aman diimpor ulang')
            ->assertSee('Biaya penyelenggaraan')
            ->assertSee('PANGI '.app(VersiAplikasi::class)->label());
    }

    /** Rincian biaya dan daftar riil dua dokumen terpisah, masing-masing bertanda tangan sendiri. */
    public function test_panduan_menjelaskan_dua_dokumen_yang_terpisah(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSee('Lampiran II PMK 113/PMK.05/2012')
            ->assertSee('Lampiran IX PMK 113/PMK.05/2012')
            ->assertSee('Menandatangani yang satu tidak ikut mengesahkan yang lain');
    }

    /**
     * Tim SDM mengelola akun pengguna tanpa menyentuh master data maupun
     * jejak audit, jadi bagian administrasinya berhenti di situ.
     */
    public function test_tim_sdm_tidak_dibacakan_menu_yang_tak_dapat_dibukanya(): void
    {
        $this->bukaSebagai(PeranPengguna::TimSdm)
            ->assertSee('Mengelola Pengguna')
            ->assertSee('Pantau siapa yang sedang aktif')
            ->assertDontSee('Atur peran dan hak akses')
            ->assertDontSee('Master Data menentukan perilaku sistem')
            ->assertDontSee('Telusuri lewat Jejak Audit');
    }

    public function test_administrator_tetap_dibacakan_peran_master_data_dan_jejak_audit(): void
    {
        $this->bukaSebagai(PeranPengguna::SuperAdministrator)
            ->assertSee('Atur peran dan hak akses')
            ->assertSee('Master Data menentukan perilaku sistem')
            ->assertSee('Telusuri lewat Jejak Audit');
    }

    // ── Unduhan buku panduan ──

    public function test_buku_panduan_dapat_diunduh_dari_dalam_sistem(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSee('Unduh Buku Panduan (PDF)')
            ->assertSee('Versi Word')
            ->assertSee(route('panduan.unduh', ['format' => 'pdf']), escape: false)
            ->assertSee(route('panduan.unduh'), escape: false)
            ->assertSee('images/panduan/sampul.webp', escape: false);

        $unduhan = $this->actingAs(User::factory()->create())
            ->get(route('panduan.unduh'))
            ->assertOk();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $unduhan->headers->get('content-type'),
        );

        $this->assertStringContainsString(
            PanduanController::BERKAS_BUKU,
            $unduhan->headers->get('content-disposition'),
        );
    }

    /** Versi PDF memuat sampul dan gambar yang sama, terbuka di perangkat apa pun. */
    public function test_buku_panduan_dapat_diunduh_sebagai_pdf(): void
    {
        $unduhan = $this->actingAs(User::factory()->create())
            ->get(route('panduan.unduh', ['format' => 'pdf']))
            ->assertOk()
            ->assertDownload(PanduanController::BERKAS_PDF);

        $this->assertSame('application/pdf', $unduhan->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-', file_get_contents($unduhan->getFile()->getPathname()));
    }

    /** Format yang tidak dikenal kembali ke bawaannya, berkas Word. */
    public function test_format_unduhan_yang_tidak_dikenal_memberikan_berkas_word(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panduan.unduh', ['format' => 'exe']))
            ->assertDownload(PanduanController::BERKAS_BUKU);
    }

    /** Buku panduan bukan berkas terbuka; pengunjung tanpa akun tidak menerimanya. */
    public function test_unduhan_tertutup_bagi_yang_belum_masuk(): void
    {
        $this->get(route('panduan.unduh'))->assertRedirect(route('login'));
        $this->get(route('panduan.unduh', ['format' => 'pdf']))->assertRedirect(route('login'));
    }

    // ── Langkah bergambar ──

    /** Langkah membuat SPD sampai mengajukan perjadin disertai tangkapan layar. */
    public function test_langkah_spd_dan_pengajuan_bergambar(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)
            ->assertSeeInOrder([
                'Menerbitkan Surat Perjalanan Dinas',
                'images/panduan/spd-01-menu.webp',
                'images/panduan/spd-02-identitas.webp',
                'images/panduan/spd-07-dokumen.webp',
                'Tandatangani lewat SRIKANDI',
                'Mengajukan Perjalanan Dinas untuk Diri Sendiri',
                'images/panduan/usulan-01-jalur.webp',
                'images/panduan/usulan-06-konfirmasi.webp',
                'images/panduan/usulan-07-daftar.webp',
            ], escape: false)
            ->assertSee('Gambar memakai data contoh dengan pegawai fiktif.');
    }

    /** Gambar yang dirujuk halaman panduan benar-benar ikut terpasang. */
    public function test_setiap_gambar_panduan_tersedia(): void
    {
        $halaman = $this->bukaSebagai(PeranPengguna::SuperAdministrator)->getContent();

        preg_match_all('#images/panduan/([a-z0-9-]+\.webp)#', $halaman, $temuan);
        $gambar = array_unique($temuan[1]);

        $this->assertGreaterThanOrEqual(16, count($gambar));
        foreach ($gambar as $berkas) {
            $this->assertFileExists(public_path('images/panduan/'.$berkas));
        }
    }

    public function test_saluran_bantuan_terbuka_bagi_seluruh_peran(): void
    {
        $this->bukaSebagai(PeranPengguna::DosenTendik)->assertSee('Melapor Kendala');
        $this->bukaSebagai(PeranPengguna::Pimpinan)->assertSee('Melapor Kendala');
    }
}
