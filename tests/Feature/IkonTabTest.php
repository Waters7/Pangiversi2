<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identitas PANGI pada tab browser: ikon, judul, dan manifes aplikasi.
 */
class IkonTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_dalam_aplikasi_memuat_ikon(): void
    {
        $isi = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('rel="icon" href="'.asset('favicon.ico'), $isi);
        $this->assertStringContainsString('images/favicon-32.png', $isi);
        $this->assertStringContainsString('rel="apple-touch-icon"', $isi);
        $this->assertStringContainsString('site.webmanifest', $isi);
        $this->assertStringContainsString('name="theme-color" content="#141d24"', $isi);
    }

    public function test_halaman_masuk_juga_memuat_ikon(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('favicon.ico', false)
            ->assertSee('apple-touch-icon', false);
    }

    public function test_halaman_verifikasi_publik_memuat_ikon(): void
    {
        $this->get(route('verifikasi.tampil', 'PPK-TIDAKADA99'))
            ->assertOk()
            ->assertSee('favicon.ico', false);
    }

    public function test_judul_tab_menyebut_halaman_dan_institusi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('<title>Panduan Penggunaan — Poltekkes Kemenkes Manado</title>', false);
    }

    public function test_berkas_ikon_tersedia(): void
    {
        foreach ([
            public_path('favicon.ico'),
            public_path('site.webmanifest'),
            public_path('images/favicon-16.png'),
            public_path('images/favicon-32.png'),
            public_path('images/favicon-180.png'),
            public_path('images/favicon-192.png'),
            public_path('images/favicon-512.png'),
        ] as $berkas) {
            $this->assertFileExists($berkas);
            $this->assertGreaterThan(0, filesize($berkas), basename($berkas).' kosong.');
        }
    }

    public function test_manifes_memuat_identitas_pangi(): void
    {
        $manifes = json_decode(file_get_contents(public_path('site.webmanifest')), true);

        $this->assertSame('PANGI', $manifes['short_name']);
        $this->assertSame('#141d24', $manifes['theme_color']);
        $this->assertCount(2, $manifes['icons']);
    }
}
