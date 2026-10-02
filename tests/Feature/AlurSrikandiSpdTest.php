<?php

namespace Tests\Feature;

use App\Models\SuratPerjalananDinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keterangan yang mengarahkan pengguna sesudah SPD dibuat: unduh dokumennya,
 * masukkan ke SRIKANDI, lalu ajukan usulan perjadin dengan SPD bertanda
 * tangan dan nomor naskah dari SRIKANDI — bukan nomor internal PANGI.
 */
class AlurSrikandiSpdTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private SuratPerjalananDinas $spd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->spd = $this->terbitkanSpd($this->pelaksana);
    }

    public function test_halaman_spd_mengarahkan_ke_srikandi_lalu_usulan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('spd.show', $this->spd))
            ->assertOk()
            ->assertSeeInOrder([
                'Langkah berikutnya',
                'Unduh PDF', 'SRIKANDI', 'PPK (lembar pertama) dan Direktur (lembar kedua)',
                'unduh SPD bertanda tangan dari SRIKANDI', 'nomor internal PANGI',
                'Usulan Perjadin → Buat Usulan Perjadin',
            ]);
    }

    public function test_formulir_usulan_mengingatkan_nomor_dari_srikandi(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['jenis' => 'luar-kota']))
            ->assertOk()
            ->assertSee('Nomor naskah yang diterbitkan SRIKANDI')
            ->assertSee('itu nomor internal SPD di PANGI');
    }
}
