<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\DaftarRiil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panduan penggunaan terbuka untuk semua peran, karena setiap pengguna
 * berhak mengajukan perjalanan dinas.
 */
class PanduanTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('panduan'))->assertRedirect(route('login'));
    }

    public function test_semua_peran_dapat_membuka_panduan(): void
    {
        foreach (PeranPengguna::cases() as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('panduan'))
                ->assertOk()
                ->assertSee('Panduan Penggunaan PANGI');
        }
    }

    public function test_panduan_memuat_seluruh_bagian_alur(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee('Mengajukan Perjadin')
            ->assertSee('Pengajuan Kelompok')
            ->assertSee('Konfirmasi Usulan')
            ->assertSee('Setelah Perjalanan')
            ->assertSee('Rincian &amp; Tanda Tangan', false);
    }

    public function test_masa_sanggah_dijelaskan_sesuai_ketentuan_yang_berlaku(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panduan'))
            ->assertOk()
            ->assertViewHas('hariSanggah', DaftarRiil::HARI_MASA_SANGGAH)
            ->assertSee((string) DaftarRiil::HARI_MASA_SANGGAH);
    }

    public function test_pintasan_panduan_tersedia_di_sidebar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panduan'))
            ->assertOk()
            ->assertSee(route('panduan'), false);
    }
}
