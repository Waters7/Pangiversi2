<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\PesanWhatsapp;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Halaman input rincian biaya menyediakan tombol WhatsApp yang membuka
 * percakapan dengan pelaksana, memakai nomor pada profil pelaksana.
 */
class HubungiPelaksanaWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const CATATAN_TANPA_NOMOR = 'Nomor WhatsApp pelaksana belum terdaftar.';

    private function usulanPelaksana(?string $nomor): Usulan
    {
        $pelaksana = User::factory()->create(['nama' => 'Maya Sari', 'no_hp' => $nomor]);
        $usulan = Usulan::factory()->create([
            'id_user' => $pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => 'Jakarta',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        return $usulan;
    }

    private function halaman(Usulan $usulan, PeranPengguna $peran = PeranPengguna::TimKeuangan): TestResponse
    {
        $petugas = User::factory()->create(['role' => $peran->value]);

        return $this->actingAs($petugas)->get(route('keuangan.detail', $usulan->no_usulan))->assertOk();
    }

    public function test_tombol_membuka_whatsapp_pelaksana_dengan_pesan_pembuka(): void
    {
        $usulan = $this->usulanPelaksana('0812-3456-7890');

        $this->halaman($usulan)
            ->assertSee('Hubungi Pelaksana')
            ->assertSee('https://wa.me/6281234567890?text=', false)
            ->assertSee(rawurlencode('Selamat pagi/siang Maya Sari'), false)
            ->assertSee(rawurlencode($usulan->no_usulan), false)
            ->assertDontSee(self::CATATAN_TANPA_NOMOR);
    }

    public function test_bendahara_juga_mendapat_tombolnya(): void
    {
        $this->halaman($this->usulanPelaksana('081234567890'), PeranPengguna::Bendahara)
            ->assertSee('data-hubungi-pelaksana', false);
    }

    public function test_tanpa_nomor_tampil_catatan_bukan_tombol(): void
    {
        $this->halaman($this->usulanPelaksana(null))
            ->assertSee(self::CATATAN_TANPA_NOMOR)
            ->assertDontSee('data-hubungi-pelaksana', false);
    }

    public function test_peran_yang_hanya_melihat_tidak_mendapat_tombol_maupun_catatan(): void
    {
        $this->halaman($this->usulanPelaksana('081234567890'), PeranPengguna::Ppk)
            ->assertDontSee('data-hubungi-pelaksana', false)
            ->assertDontSee(self::CATATAN_TANPA_NOMOR);
    }

    public function test_pesan_mengikuti_format_yang_diatur_dan_bukan_pesan_server(): void
    {
        $this->assertFalse(PesanWhatsapp::HubungiPelaksana->pesanServer());

        $admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $this->actingAs($admin)->get(route('administrasi.format-whatsapp'))
            ->assertOk()
            ->assertSee(PesanWhatsapp::HubungiPelaksana->label());
    }
}
