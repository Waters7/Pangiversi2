<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dokumen rincian biaya memuat dua QR konfirmasi: dari pelaksana setelah
 * daftar riilnya ditandatangani PPK, dan dari bendahara setelah lunas.
 */
class QrRincianBiayaTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private User $pelaksana;

    private User $bendahara;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create([
            'role' => PeranPengguna::DosenTendik->value,
            'nama' => 'Rahmatullah Pontoh',
        ]);
        $this->bendahara = User::factory()->create([
            'role' => PeranPengguna::Bendahara->value,
            'nama' => 'Mery Diana Koraag',
        ]);
        $this->ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'peran' => 'ketua',
        ]);

        $keuangan = Keuangan::factory()->belumBayar()->create([
            'id_usulan' => $this->usulan->id,
        ]);

        RincianBiaya::factory()->create([
            'id_keuangan' => $keuangan->id,
            'kategori' => KategoriBiaya::UangHarian->value,
            'volume' => 3,
            'harga_satuan' => 530_000,
            'jumlah' => 1_590_000,
        ]);

        $keuangan->hitungTotal();
    }

    private function daftarRiilDitandatangani(): DaftarRiil
    {
        $daftar = DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 1_590_000,
        ]);

        $daftar->kirimKePegawai();
        $daftar->setujuiPegawai();
        $daftar->tandaTangani($this->ppk);

        return $daftar->fresh();
    }

    private function lunasi(): Keuangan
    {
        // Pelunasan baru boleh keluar setelah daftar nominatif surat
        // tugasnya ditandatangani PPK dan diterima tim keuangan, dan
        // laporan perjalanannya dikonfirmasi pimpinan.
        $this->terbitkanNominatif($this->usulan);
        $this->konfirmasiLaporan($this->usulan);

        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-uang-muka', $this->usulan), [
                'tanggal_transfer' => today()->subDays(3)->toDateString(),
                'bukti_transfer' => UploadedFile::fake()->create('um.pdf', 50, 'application/pdf'),
            ]);

        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-sisa', $this->usulan), [
                'tanggal_pelunasan' => today()->toDateString(),
                'bukti_pelunasan' => UploadedFile::fake()->create('lunas.pdf', 50, 'application/pdf'),
            ]);

        return $this->usulan->keuangan->fresh();
    }

    private function cetak(): string
    {
        return $this->actingAs($this->bendahara)
            ->get(route('keuangan.cetak-rincian', [$this->usulan, 'peserta' => $this->peserta->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();
    }

    // ── Kode konfirmasi bendahara ──

    public function test_pelunasan_menerbitkan_kode_konfirmasi_bendahara(): void
    {
        $keuangan = $this->lunasi();

        $this->assertNotNull($keuangan->kode_konfirmasi_bayar);
        $this->assertStringStartsWith('BND-', $keuangan->kode_konfirmasi_bayar);
        $this->assertNotNull($keuangan->dikonfirmasi_bayar_at);
        $this->assertTrue($keuangan->sudahDikonfirmasiBayar());
    }

    public function test_uang_muka_saja_belum_menerbitkan_kode(): void
    {
        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-uang-muka', $this->usulan), [
                'tanggal_transfer' => today()->toDateString(),
                'bukti_transfer' => UploadedFile::fake()->create('um.pdf', 50, 'application/pdf'),
            ]);

        $this->assertNull($this->usulan->keuangan->fresh()->kode_konfirmasi_bayar);
    }

    public function test_kode_bertahan_saat_pelunasan_dicatat_ulang(): void
    {
        $kode = $this->lunasi()->kode_konfirmasi_bayar;

        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-sisa', $this->usulan), [
                'tanggal_pelunasan' => today()->toDateString(),
                'bukti_pelunasan' => UploadedFile::fake()->create('lunas2.pdf', 50, 'application/pdf'),
            ]);

        $this->assertSame($kode, $this->usulan->keuangan->fresh()->kode_konfirmasi_bayar);
    }

    public function test_status_yang_diturunkan_mencabut_kode(): void
    {
        $kode = $this->lunasi()->kode_konfirmasi_bayar;

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]))
            ->put(route('keuangan.koreksi-status', $this->usulan), [
                'status_keuangan' => Keuangan::STATUS_SEBAGIAN,
            ]);

        $this->assertNull($this->usulan->keuangan->fresh()->kode_konfirmasi_bayar);

        $this->get(route('verifikasi.tampil', $kode))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terverifikasi');
    }

    // ── Halaman verifikasi publik ──

    public function test_kode_bendahara_dapat_diverifikasi_tanpa_login(): void
    {
        $keuangan = $this->lunasi();

        $this->get(route('verifikasi.tampil', $keuangan->kode_konfirmasi_bayar))
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi')
            ->assertSee($this->usulan->no_usulan)
            ->assertSee($keuangan->kode_konfirmasi_bayar)
            ->assertSee('Nomor Konfirmasi Pembayaran')
            ->assertSee('Tanggal Konfirmasi Pembayaran')
            ->assertSee('Bendahara Pengeluaran')
            ->assertSee('Mery Diana Koraag');
    }

    public function test_ketiga_jenis_kode_dikenali_terpisah(): void
    {
        $daftar = $this->daftarRiilDitandatangani();
        $keuangan = $this->lunasi();

        $this->get(route('verifikasi.tampil', $daftar->kode_verifikasi))
            ->assertOk()->assertSee('Pejabat Pembuat Komitmen');

        $this->get(route('verifikasi.tampil', $daftar->kode_konfirmasi))
            ->assertOk()->assertSee('Pelaksana Perjalanan Dinas');

        $this->get(route('verifikasi.tampil', $keuangan->kode_konfirmasi_bayar))
            ->assertOk()->assertSee('Bendahara Pengeluaran');
    }

    // ── QR pada dokumen rincian biaya ──

    public function test_tanpa_tanda_tangan_dan_pelunasan_belum_ada_qr(): void
    {
        $html = $this->renderRincian();

        $this->assertStringNotContainsString('data:image/png;base64,', $html);
    }

    public function test_tanda_tangan_ppk_memunculkan_qr_pelaksana_dan_ppk(): void
    {
        $daftar = $this->daftarRiilDitandatangani();
        $html = $this->renderRincian();

        // Dua QR sekaligus: konfirmasi pelaksana dan tanda tangan PPK.
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringContainsString($daftar->kode_konfirmasi, $html);
        $this->assertStringContainsString('Nominal dikonfirmasi pelaksana', $html);
    }

    public function test_qr_ppk_memuat_kode_verifikasinya(): void
    {
        $daftar = $this->daftarRiilDitandatangani();
        $html = $this->renderRincian();

        $this->assertStringContainsString($daftar->kode_verifikasi, $html);
        $this->assertStringContainsString('Ditandatangani secara elektronik', $html);
        $this->assertStringContainsString('keabsahan tanda tangan PPK', $html);
    }

    public function test_qr_ppk_isinya_sama_dengan_qr_daftar_riil(): void
    {
        $daftar = $this->daftarRiilDitandatangani();

        $this->assertSame(
            route('verifikasi.tampil', $daftar->kode_verifikasi),
            $daftar->urlVerifikasi()
        );
    }

    public function test_tanpa_tanda_tangan_ppk_belum_ada_qr_ppk(): void
    {
        $html = $this->renderRincian();

        $this->assertStringNotContainsString('keabsahan tanda tangan PPK', $html);
    }

    public function test_qr_bendahara_muncul_setelah_lunas(): void
    {
        $keuangan = $this->lunasi();
        $html = $this->renderRincian();

        $this->assertSame(1, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringContainsString($keuangan->kode_konfirmasi_bayar, $html);
        $this->assertStringContainsString('Pembayaran dikonfirmasi lunas', $html);
    }

    public function test_ketiga_qr_tercetak_saat_lengkap(): void
    {
        $daftar = $this->daftarRiilDitandatangani();
        $keuangan = $this->lunasi();
        $html = $this->renderRincian();

        // Pelaksana, PPK, dan bendahara masing-masing punya QR sendiri.
        $this->assertSame(3, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringContainsString($daftar->kode_konfirmasi, $html);
        $this->assertStringContainsString($daftar->kode_verifikasi, $html);
        $this->assertStringContainsString($keuangan->kode_konfirmasi_bayar, $html);
    }

    public function test_qr_pelaksana_isinya_sama_dengan_qr_daftar_riil(): void
    {
        $daftar = $this->daftarRiilDitandatangani();

        // Sumber QR pada kedua dokumen adalah URL konfirmasi yang sama.
        $this->assertSame(
            route('verifikasi.tampil', $daftar->kode_konfirmasi),
            $daftar->urlKonfirmasi()
        );

        $this->assertStringContainsString($daftar->kode_konfirmasi, $this->renderRincian());
    }

    public function test_pdf_rincian_dapat_diunduh(): void
    {
        $this->daftarRiilDitandatangani();
        $this->lunasi();

        $this->assertGreaterThan(3000, strlen($this->cetak()));
    }

    /**
     * Render templat cetaknya dengan data yang sama seperti controller,
     * agar isinya dapat diperiksa tanpa membongkar berkas PDF.
     */
    private function renderRincian(): string
    {
        $usulan = $this->usulan->fresh(['user.unit', 'kegiatan', 'keuangan.rincianBiaya', 'peserta']);
        $keuangan = $usulan->keuangan;
        $qr = app(QrCodeService::class);

        $daftar = DaftarRiil::where('id_peserta', $this->peserta->id)->sudahDitandatangani()->first();

        return view('keuangan.cetak-rincian', [
            'usulan' => $usulan,
            'peserta' => $this->peserta,
            'rincianPerKategori' => $keuangan->rincianBiaya->groupBy(fn ($i) => $i->kategori->value),
            'total' => (float) $keuangan->rincianBiaya->sum('jumlah'),
            'transportLokal' => collect(),
            'totalTransportLokal' => 0.0,
            'totalKeseluruhan' => (float) $keuangan->rincianBiaya->sum('jumlah'),
            'dibayarkan' => (float) $keuangan->uang_muka,
            'terbilang' => 'satu juta lima ratus sembilan puluh ribu rupiah',
            'bendahara' => $this->bendahara,
            'ppk' => $this->ppk,
            'daftarRiil' => $daftar,
            'keuangan' => $keuangan,
            'qrPelaksana' => $daftar?->urlKonfirmasi() ? $qr->dataUri($daftar->urlKonfirmasi(), 180) : null,
            'qrPpk' => $daftar?->urlVerifikasi() ? $qr->dataUri($daftar->urlVerifikasi(), 180) : null,
            'qrBendahara' => $keuangan?->urlKonfirmasiBayar() ? $qr->dataUri($keuangan->urlKonfirmasiBayar(), 180) : null,
        ])->render();
    }
}
