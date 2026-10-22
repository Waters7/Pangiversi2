<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\Kemampuan;
use App\Enums\MenuAplikasi;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\KategoriPerjadin;
use App\Models\Notifikasi;
use App\Models\Peran;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Usulan yang salah jalur — misalnya lintas pulau yang terlanjur diajukan
 * lewat jalur dalam satu provinsi — dipindahkan peran yang berwenang,
 * dengan alasan yang tercatat.
 */
class UbahJalurPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelaksana;

    private Usulan $usulan;

    private KategoriPerjadin $kategoriLuarKota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(KategoriPerjadinSeeder::class);

        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value, 'nama' => 'Admin Sistem']);
        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->kategoriLuarKota = KategoriPerjadin::aktif()
            ->where('dalam_kota', false)
            ->where('grup', '!=', JenisPerjadin::GRUP_SUPERVISI)
            ->where('kode', '!=', KategoriPerjadin::KODE_LUAR_NEGERI)
            ->firstOrFail();

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'jenis_perjadin' => JenisPerjadin::LuarKotaDalamProvinsi->value,
            'id_kategori_perjadin' => $this->kategoriLuarKota->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function ubahJalur(User $pengguna, array $isian = []): TestResponse
    {
        return $this->actingAs($pengguna)->put(route('usulan.jalur', $this->usulan), $isian + [
            'jalur' => JenisPerjadin::LuarKota->value,
            'id_kategori_perjadin' => $this->kategoriLuarKota->id,
            'alasan' => 'Tujuan lintas pulau, terlanjur diajukan lewat jalur dalam provinsi.',
        ]);
    }

    public function test_hak_ubah_jalur_berada_pada_menu_usulan_perjadin(): void
    {
        $this->assertSame(MenuAplikasi::UsulanPerjadin, MenuAplikasi::untuk(Kemampuan::MengubahJalurPerjadin));
        $this->assertFalse(PeranPengguna::Ppk->punya(Kemampuan::MengubahJalurPerjadin));
    }

    public function test_admin_memindahkan_usulan_ke_jalur_lintas_pulau(): void
    {
        $this->actingAs($this->admin)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Perjalanan Luar Kota Dalam Satu Provinsi')
            ->assertSee('Ubah Jalur Perjadin');

        $this->ubahJalur($this->admin)
            ->assertRedirect(route('usulan.show', $this->usulan))
            ->assertSessionHas('success');

        $usulan = $this->usulan->fresh();
        $this->assertSame(JenisPerjadin::LuarKota, $usulan->jalur());
        $this->assertSame($this->kategoriLuarKota->id, $usulan->id_kategori_perjadin);

        $this->assertTrue(AuditLog::where('id_usulan', $usulan->id)
            ->where('id_user', $this->admin->id)
            ->where('deskripsi', 'like', '%ke Perjalanan Luar Kota Lintas Pulau%Alasan: Tujuan lintas pulau%')
            ->exists());
        $this->assertDatabaseHas(Notifikasi::class, [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Jalur perjalanan dinas diubah',
        ]);
    }

    public function test_peran_yang_diberi_hak_dapat_mengubah_jalur(): void
    {
        Peran::sinkronBawaan();
        Peran::where('kode', PeranPengguna::TimSdm->value)->sole()
            ->aturHakAkses([Kemampuan::MengajukanUsulan, Kemampuan::MengubahJalurPerjadin]);
        $operator = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);

        $this->ubahJalur($operator)->assertSessionHas('success');

        $this->assertSame(JenisPerjadin::LuarKota, $this->usulan->fresh()->jalur());
    }

    public function test_tanpa_hak_jalur_tidak_dapat_diubah(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertDontSee('Ubah Jalur Perjadin');

        $this->ubahJalur($this->pelaksana)->assertForbidden();

        $this->assertSame(JenisPerjadin::LuarKotaDalamProvinsi, $this->usulan->fresh()->jalur());
    }

    public function test_kategori_harus_tersedia_pada_jalur_baru_dan_alasan_wajib(): void
    {
        $kategoriDalamKota = KategoriPerjadin::aktif()
            ->where('dalam_kota', true)
            ->where('grup', '!=', JenisPerjadin::GRUP_SUPERVISI)
            ->firstOrFail();

        $this->ubahJalur($this->admin, ['id_kategori_perjadin' => $kategoriDalamKota->id])
            ->assertSessionHasErrors('id_kategori_perjadin');
        $this->ubahJalur($this->admin, ['alasan' => ''])->assertSessionHasErrors('alasan');

        $this->assertSame(JenisPerjadin::LuarKotaDalamProvinsi, $this->usulan->fresh()->jalur());
    }
}
