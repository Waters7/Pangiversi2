<?php

namespace Tests\Feature;

use App\Models\SuratPerjalananDinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SPD memuat nama, NIP, dan pangkat pegawai, jadi daftarnya tidak dibuka
 * untuk semua peran: hanya administrator dan Tim SDM yang melihat seluruh
 * SPD, sisanya hanya yang berkaitan dengan dirinya.
 */
class AksesDaftarSpdTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    private SuratPerjalananDinas $spdOrangLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pemilik = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'Pemilik Surat',
        ]);

        $this->spdOrangLain = $this->terbitkanSpd($this->pemilik);
        $this->spdOrangLain->update(['tempat_tujuan' => 'Kota Rahasia']);
    }

    private function pengguna(string $peran): User
    {
        return User::factory()->create(['role' => $peran]);
    }

    // ── Yang berhak melihat seluruh SPD ──

    public function test_super_administrator_melihat_seluruh_spd(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_SUPER_ADMIN))
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('Kota Rahasia')
            ->assertSee('Seluruh SPD yang pernah dibuat');
    }

    public function test_tim_sdm_melihat_seluruh_spd(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_TIM_SDM))
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('Kota Rahasia');
    }

    public function test_daftar_lengkap_menyebutkan_pembuat_tiap_spd(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_TIM_SDM))
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('Dibuat Oleh')
            ->assertSee('Pemilik Surat');
    }

    // ── Yang hanya melihat miliknya ──

    /**
     * @return list<array{string}>
     */
    public static function peranTanpaAksesPenuh(): array
    {
        return [
            [User::ROLE_PPK],
            [User::ROLE_PIMPINAN],
            [User::ROLE_BENDAHARA],
            [User::ROLE_TIM_KEUANGAN],
            [User::ROLE_DOSEN_TENDIK],
        ];
    }

    #[DataProvider('peranTanpaAksesPenuh')]
    public function test_peran_lain_hanya_melihat_spd_yang_berkaitan_dengannya(string $peran): void
    {
        $this->actingAs($this->pengguna($peran))
            ->get(route('spd.index'))
            ->assertOk()
            ->assertDontSee('Kota Rahasia')
            ->assertSee('Daftar SPD yang Anda buat atau yang mencantumkan nama Anda');
    }

    public function test_pemiliknya_sendiri_tetap_melihat_spd_itu(): void
    {
        $this->actingAs($this->pemilik)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee('Kota Rahasia');
    }

    // ── Membuka satu berkas ──

    public function test_tim_sdm_dapat_membuka_dan_mencetak_spd_siapa_pun(): void
    {
        $sdm = $this->pengguna(User::ROLE_TIM_SDM);

        $this->actingAs($sdm)->get(route('spd.show', $this->spdOrangLain))->assertOk();
        $this->actingAs($sdm)->get(route('spd.cetak', $this->spdOrangLain))->assertOk();
    }

    public function test_ppk_tidak_dapat_membuka_spd_orang_lain(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_PPK))
            ->get(route('spd.show', $this->spdOrangLain))
            ->assertForbidden();
    }

    // ── Menyunting tetap milik yang bersangkutan ──

    /**
     * Kewenangan membaca arsip bukan kewenangan meralatnya: Tim SDM melihat
     * seluruh SPD, tetapi tidak boleh mengubah surat orang lain.
     */
    public function test_tim_sdm_tidak_dapat_menyunting_spd_orang_lain(): void
    {
        $sdm = $this->pengguna(User::ROLE_TIM_SDM);

        $this->actingAs($sdm)->get(route('spd.edit', $this->spdOrangLain))->assertForbidden();
        $this->actingAs($sdm)->delete(route('spd.destroy', $this->spdOrangLain))->assertForbidden();

        $this->assertDatabaseHas('surat_perjalanan_dinas', ['id' => $this->spdOrangLain->id]);
    }

    public function test_tautan_ubah_disembunyikan_bila_tak_berwenang(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_TIM_SDM))
            ->get(route('spd.index'))
            ->assertOk()
            ->assertDontSee(route('spd.edit', $this->spdOrangLain), false);
    }

    public function test_pemiliknya_masih_melihat_tautan_ubah(): void
    {
        $this->actingAs($this->pemilik)
            ->get(route('spd.index'))
            ->assertOk()
            ->assertSee(route('spd.edit', $this->spdOrangLain), false);
    }

    public function test_super_administrator_tetap_dapat_menyunting(): void
    {
        $this->actingAs($this->pengguna(User::ROLE_SUPER_ADMIN))
            ->get(route('spd.edit', $this->spdOrangLain))
            ->assertOk();
    }
}
