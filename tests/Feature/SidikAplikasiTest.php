<?php

namespace Tests\Feature;

use App\Services\SidikAplikasi;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sidik keaslian kode sumber: tersimpan terenkripsi, hanya dibuka lewat
 * perintah tersembunyi. Isinya dicocokkan lewat SHA-256 supaya teksnya
 * tidak ikut tertulis terbuka di sini.
 */
class SidikAplikasiTest extends TestCase
{
    private const HASH_SIDIK = '37e4c400ef42e94ea522f546f9969775553e6a019a87a987a766664ea833b5d6';

    public function test_sidik_terbuka_ke_isi_yang_tercatat(): void
    {
        $sidik = app(SidikAplikasi::class);

        $this->assertNotSame('', $sidik->teks());
        $this->assertSame(self::HASH_SIDIK, $sidik->hash());
    }

    public function test_isi_sidik_tidak_tertulis_terbuka_di_kode_sumber(): void
    {
        $kode = (string) file_get_contents(app_path('Services/SidikAplikasi.php'));

        $this->assertStringNotContainsString(app(SidikAplikasi::class)->teks(), $kode);
    }

    public function test_perintah_sidik_tersembunyi_dan_membuka_isinya(): void
    {
        $this->assertTrue(Artisan::all()['pangi:sidik']->isHidden());

        $this->artisan('pangi:sidik')
            ->expectsOutputToContain(app(SidikAplikasi::class)->teks())
            ->expectsOutputToContain(self::HASH_SIDIK)
            ->assertSuccessful();
    }
}
