<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Token API dashboard eksekutif dibuat dan dicabut super administrator dari
 * Administrasi Sistem, tanpa menyentuh .env di server.
 */
class TokenApiAdministrasiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_ENV = 'token-dari-env-yang-cukup-panjang-untuk-dipakai';

    private function admin(): User
    {
        return User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    public function test_halaman_pengaturan_menampilkan_token_yang_berlaku_dari_env(): void
    {
        config(['api.token' => self::TOKEN_ENV]);

        $this->actingAs($this->admin())
            ->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertSee('Token API Dashboard Eksekutif')
            ->assertSee('Aktif · dari berkas .env')
            ->assertSee(self::TOKEN_ENV)
            ->assertSee('Ganti Token')
            ->assertDontSee('Cabut Token')
            ->assertSee(url('/api/v1/dashboard-eksekutif'));
    }

    public function test_tanpa_token_halaman_menyebut_api_tertutup(): void
    {
        config(['api.token' => null]);

        $this->actingAs($this->admin())
            ->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertSee('API tertutup — token belum dipasang')
            ->assertSee('Buat Token')
            ->assertSee('Buat token API?');
    }

    public function test_token_buatan_administrator_menggantikan_token_env(): void
    {
        config(['api.token' => self::TOKEN_ENV]);

        $this->actingAs($this->admin())
            ->post(route('administrasi.token-api.buat'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $baru = Pengaturan::tokenApi();

        $this->assertSame('pengaturan', $baru['sumber']);
        $this->assertSame(64, strlen($baru['token']));
        $this->assertDatabaseHas('audit_logs', [
            'deskripsi' => 'Token API dashboard eksekutif diganti; token sebelumnya tidak berlaku lagi.',
        ]);

        // API menerima token baru dan menolak token lama dari .env.
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer '.$baru['token']])
            ->assertOk();
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer '.self::TOKEN_ENV])
            ->assertStatus(401);

        // Halaman menampilkan token baru beserta tombol cabut.
        $this->actingAs($this->admin())
            ->get(route('administrasi.pengaturan'))
            ->assertSee($baru['token'])
            ->assertSee('Cabut Token')
            ->assertSee('Ganti token API?');
    }

    public function test_mencabut_token_mengembalikan_ke_env_atau_menutup_api(): void
    {
        config(['api.token' => self::TOKEN_ENV]);
        Pengaturan::simpan([Pengaturan::TOKEN_API => str_repeat('a', 64)]);

        $this->actingAs($this->admin())
            ->delete(route('administrasi.token-api.cabut'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Token API dicabut. API kembali memakai token dari berkas .env server.');

        $this->assertSame('env', Pengaturan::tokenApi()['sumber']);
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer '.self::TOKEN_ENV])
            ->assertOk();

        // Tanpa token di .env, pencabutan menutup API sepenuhnya.
        config(['api.token' => null]);
        Pengaturan::simpan([Pengaturan::TOKEN_API => str_repeat('b', 64)]);

        $this->actingAs($this->admin())
            ->delete(route('administrasi.token-api.cabut'))
            ->assertSessionHas('success', 'Token API dicabut. API tertutup sampai token baru dibuat.');

        $this->assertNull(Pengaturan::tokenApi()['sumber']);
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer '.str_repeat('b', 64)])
            ->assertStatus(503);
    }

    public function test_hanya_super_administrator_yang_melihat_dan_mengatur_token(): void
    {
        config(['api.token' => self::TOKEN_ENV]);
        $timSdm = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);

        $this->actingAs($timSdm)
            ->get(route('administrasi.pengaturan'))
            ->assertOk()
            ->assertDontSee('Token API Dashboard Eksekutif')
            ->assertDontSee(self::TOKEN_ENV);

        $this->actingAs($timSdm)->post(route('administrasi.token-api.buat'))->assertForbidden();
        $this->actingAs($timSdm)->delete(route('administrasi.token-api.cabut'))->assertForbidden();

        $this->assertSame('env', Pengaturan::tokenApi()['sumber']);
    }
}
