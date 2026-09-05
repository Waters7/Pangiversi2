<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifikasiQrTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $this->peserta = PesertaUsulan::factory()->create(['id_usulan' => $this->usulan->id]);
        $this->ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);
    }

    private function tandaTangani(): DaftarRiil
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 1_500_000,
        ]);

        // PPK baru boleh menandatangani setelah pelaksana menyetujui nominalnya.
        $this->setujuiSebagaiPelaksana($this->peserta);

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        return DaftarRiil::firstWhere('id_peserta', $this->peserta->id);
    }

    /**
     * Jalankan langkah kirim-ke-pelaksana lalu persetujuannya.
     */
    private function setujuiSebagaiPelaksana(PesertaUsulan $peserta): void
    {
        DaftarRiil::firstWhere('id_peserta', $peserta->id)?->kirimKePegawai();

        $pelaksana = $peserta->user ?? User::factory()->create();
        $peserta->update(['id_user' => $pelaksana->id]);

        $this->actingAs($pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $peserta]));
    }

    // ── Kode verifikasi ──

    public function test_tanda_tangan_menghasilkan_kode_verifikasi(): void
    {
        $daftar = $this->tandaTangani();

        $this->assertNotNull($daftar->kode_verifikasi);
        $this->assertStringStartsWith('PPK-', $daftar->kode_verifikasi);
        $this->assertNotNull($daftar->diajukan_at);
    }

    public function test_kode_verifikasi_bersifat_unik(): void
    {
        $pesertaLain = PesertaUsulan::factory()->create(['id_usulan' => $this->usulan->id]);

        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $pesertaLain->id,
            'total_riil' => 900_000,
        ]);

        $pertama = $this->tandaTangani();

        $this->setujuiSebagaiPelaksana($pesertaLain);

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $pesertaLain]));

        $kedua = DaftarRiil::firstWhere('id_peserta', $pesertaLain->id);

        $this->assertNotSame($pertama->kode_verifikasi, $kedua->kode_verifikasi);
    }

    public function test_kode_bertahan_saat_ditandatangani_ulang(): void
    {
        $daftar = $this->tandaTangani();
        $kodeAwal = $daftar->kode_verifikasi;

        // Menandatangani ulang tanpa dibatalkan tidak menerbitkan kode baru.
        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        $this->assertSame($kodeAwal, $daftar->fresh()->kode_verifikasi);
    }

    public function test_pembatalan_tanda_tangan_mencabut_kode_verifikasi(): void
    {
        $daftar = $this->tandaTangani();
        $kode = $daftar->kode_verifikasi;

        $this->actingAs($this->ppk)
            ->delete(route('daftar-riil.batal-tanda-tangan', [$this->usulan, $this->peserta]));

        $this->assertNull($daftar->fresh()->kode_verifikasi);

        // Dokumen yang sudah beredar tidak lagi tervalidasi.
        $this->get(route('verifikasi.tampil', $kode))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terverifikasi');
    }

    // ── Halaman verifikasi publik ──

    public function test_halaman_verifikasi_terbuka_tanpa_login(): void
    {
        $daftar = $this->tandaTangani();

        $this->get(route('verifikasi.tampil', $daftar->kode_verifikasi))
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi');
    }

    public function test_halaman_verifikasi_menampilkan_tiga_data_legalitas(): void
    {
        $daftar = $this->tandaTangani();

        $this->get(route('verifikasi.tampil', $daftar->kode_verifikasi))
            ->assertOk()
            ->assertSee($daftar->kode_verifikasi)
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('Tanggal Pengajuan')
            ->assertSee('Tanggal Verifikasi')
            ->assertSee($this->ppk->nama);
    }

    public function test_halaman_verifikasi_tidak_membocorkan_nominal_biaya(): void
    {
        $daftar = $this->tandaTangani();

        $this->get(route('verifikasi.tampil', $daftar->kode_verifikasi))
            ->assertOk()
            ->assertDontSee('1.500.000');
    }

    public function test_kode_yang_tidak_dikenali_ditolak(): void
    {
        $this->get(route('verifikasi.tampil', 'PPK-TIDAKADA99'))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terverifikasi');
    }

    // ── QR pada PDF ──

    public function test_qr_code_menghasilkan_data_uri_png(): void
    {
        $uri = app(QrCodeService::class)->dataUri('https://pangi.test/verifikasi/PPK-ABC123');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_pdf_daftar_riil_dapat_dicetak_setelah_ditandatangani(): void
    {
        $this->tandaTangani();

        $this->actingAs($this->ppk)
            ->get(route('daftar-riil.cetak', [$this->usulan, $this->peserta]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_url_verifikasi_kosong_selama_belum_ditandatangani(): void
    {
        $daftar = DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 700_000,
        ]);

        $this->assertNull($daftar->urlVerifikasi());
    }
}
