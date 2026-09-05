<?php

namespace Tests\Feature;

use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Services\TautanVerifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QR pada dokumen cetak harus menunjuk alamat yang sama bagi siapa pun yang
 * memindainya. Bila alamatnya ikut cara dokumen dibuat, dokumen yang dicetak
 * sambil membuka aplikasi lewat localhost menghasilkan QR berisi "localhost"
 * — dan QR itu mustahil dipindai dari ponsel.
 */
class TautanVerifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function tautan(): TautanVerifikasi
    {
        return app(TautanVerifikasi::class);
    }

    public function test_alamat_dibangun_dari_app_url_bukan_dari_host_permintaan(): void
    {
        config(['app.url' => 'https://pangi.poltekkes-manado.ac.id']);

        $this->assertSame(
            'https://pangi.poltekkes-manado.ac.id/verifikasi/PPK-ABC123',
            $this->tautan()->untuk('PPK-ABC123')
        );
    }

    public function test_alamat_dengan_porta_dipertahankan(): void
    {
        config(['app.url' => 'http://172.16.70.245:8000']);

        $this->assertSame(
            'http://172.16.70.245:8000/verifikasi/BND-XYZ789',
            $this->tautan()->untuk('BND-XYZ789')
        );
    }

    /**
     * APP_URL pernah terisi beserta jalurnya, yang membuat setiap tautan
     * meleset satu tingkat. Jalur seperti itu harus diabaikan.
     */
    public function test_jalur_yang_terlanjur_menempel_pada_app_url_diabaikan(): void
    {
        config(['app.url' => 'https://pangi.poltekkes-manado.ac.id/list-usulan']);

        $this->assertSame(
            'https://pangi.poltekkes-manado.ac.id/verifikasi/PLK-QWERTY',
            $this->tautan()->untuk('PLK-QWERTY')
        );
    }

    public function test_garis_miring_di_ujung_tidak_menggandakan_pemisah(): void
    {
        config(['app.url' => 'https://pangi.poltekkes-manado.ac.id/']);

        $this->assertSame(
            'https://pangi.poltekkes-manado.ac.id/verifikasi/PPK-SATU',
            $this->tautan()->untuk('PPK-SATU')
        );
    }

    public function test_kode_kosong_menghasilkan_null(): void
    {
        $this->assertNull($this->tautan()->untuk(null));
        $this->assertNull($this->tautan()->untuk(''));
    }

    // ── Dipakai oleh model ──

    public function test_qr_daftar_riil_memakai_app_url(): void
    {
        config(['app.url' => 'https://pangi.poltekkes-manado.ac.id']);

        $riil = DaftarRiil::factory()->create([
            'kode_verifikasi' => 'PPK-TANDA1',
            'kode_konfirmasi' => 'PLK-TANDA2',
            'ditandatangani_at' => now(),
        ]);

        $this->assertSame('https://pangi.poltekkes-manado.ac.id/verifikasi/PPK-TANDA1', $riil->urlVerifikasi());
        $this->assertSame('https://pangi.poltekkes-manado.ac.id/verifikasi/PLK-TANDA2', $riil->urlKonfirmasi());
    }

    public function test_qr_bendahara_memakai_app_url(): void
    {
        config(['app.url' => 'https://pangi.poltekkes-manado.ac.id']);

        $keuangan = Keuangan::factory()->lunas()->create(['kode_konfirmasi_bayar' => 'BND-BAYAR1']);

        $this->assertSame(
            'https://pangi.poltekkes-manado.ac.id/verifikasi/BND-BAYAR1',
            $keuangan->urlKonfirmasiBayar()
        );
    }

    /**
     * Inti perbaikannya: alamat QR tidak boleh berubah hanya karena halaman
     * dibuka lewat localhost.
     */
    public function test_alamat_qr_tidak_berubah_meski_halaman_dibuka_lewat_localhost(): void
    {
        config(['app.url' => 'http://172.16.70.245:8000']);

        $riil = DaftarRiil::factory()->create([
            'kode_verifikasi' => 'PPK-TETAP',
            'ditandatangani_at' => now(),
        ]);

        $dariLocalhost = $this->get('http://localhost/verifikasi/PPK-TETAP');
        $dariLocalhost->assertOk();

        $this->assertSame(
            'http://172.16.70.245:8000/verifikasi/PPK-TETAP',
            $riil->fresh()->urlVerifikasi()
        );
    }
}
