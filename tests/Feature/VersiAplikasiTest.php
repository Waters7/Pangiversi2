<?php

namespace Tests\Feature;

use App\Services\VersiAplikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Versi yang tayang tertera di halaman masuk, dan perintah pemeriksaan
 * pemasangan benar-benar mencoba QR dan PDF — bukan sekadar membaca
 * daftar ekstensi.
 */
class VersiAplikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_versi_dibaca_dari_berkas_version(): void
    {
        $versi = app(VersiAplikasi::class);

        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), $versi->nomor());
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $versi->nomor());
    }

    public function test_label_menyebut_nomor_dan_commit_bila_ada(): void
    {
        $versi = app(VersiAplikasi::class);
        $label = $versi->label();

        $this->assertStringStartsWith('v'.$versi->nomor(), $label);

        if ($versi->komit() !== null) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{7}$/', $versi->komit());
            $this->assertStringEndsWith(' · '.$versi->komit(), $label);
        }
    }

    public function test_halaman_masuk_menampilkan_versi(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('PANGI '.app(VersiAplikasi::class)->label());
    }

    public function test_pemeriksaan_pemasangan_mencoba_qr_dan_pdf(): void
    {
        $this->artisan('pangi:periksa')
            ->expectsOutputToContain('QR terbentuk')
            ->expectsOutputToContain('PDF folio terbentuk')
            ->expectsOutputToContain('public/storage menunjuk storage/app/public')
            ->expectsOutputToContain('Seluruh pemeriksaan lolos')
            ->assertSuccessful();
    }
}
