<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jenis kegiatan sempat dilepas dari formulir sebelum dipasang kembali,
 * sehingga ada usulan lama yang kolomnya kosong. Seluruh halaman harus tetap
 * terbuka untuk usulan seperti itu, bukan gagal karena mengira kolomnya
 * selalu terisi.
 */
class UsulanTanpaKegiatanTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'id_kegiatan' => null,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create(['nama' => 'Luar Kota FullBoard'])->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_daftar_usulan_menampilkan_kategori_sebagai_pengganti_jenis_kegiatan(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard');
    }

    public function test_detail_usulan_terbuka_tanpa_jenis_kegiatan(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard')
            ->assertDontSee('Jenis Kegiatan');
    }

    public function test_daftar_dokumen_terbuka_tanpa_jenis_kegiatan(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard');
    }

    public function test_formulir_dokumen_terbuka_tanpa_jenis_kegiatan(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard');
    }

    public function test_daftar_keuangan_terbuka_tanpa_jenis_kegiatan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard');
    }

    public function test_daftar_persetujuan_terbuka_tanpa_jenis_kegiatan(): void
    {
        $this->usulan->update(['status' => StatusUsulan::MenungguPpk->value]);

        $this->actingAs($this->admin())
            ->get(route('persetujuan'))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard');
    }

    public function test_detail_persetujuan_terbuka_tanpa_jenis_kegiatan(): void
    {
        $this->usulan->update(['status' => StatusUsulan::MenungguPpk->value]);

        $this->actingAs($this->admin())
            ->get(route('persetujuan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Luar Kota FullBoard');
    }

    public function test_pencarian_daftar_usulan_mengenali_kategori_perjadin(): void
    {
        Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'id_kegiatan' => null,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create(['nama' => 'Transport Lokal'])->id,
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.list', ['search' => 'Transport Lokal']))
            ->assertOk()
            ->assertSee('Transport Lokal')
            ->assertDontSee('Luar Kota FullBoard');
    }

    /**
     * Filter kepemilikan harus dikurung; tanpa itu OR di dalamnya membatalkan
     * filter status maupun pencarian yang di-AND-kan sesudahnya.
     */
    public function test_filter_status_tetap_menyaring_usulan_milik_sendiri(): void
    {
        Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'id_kegiatan' => null,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create(['nama' => 'Transport Lokal'])->id,
            'status' => StatusUsulan::Draft->value,
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.list', ['status' => StatusUsulan::Draft->value]))
            ->assertOk()
            ->assertSee('Transport Lokal')
            ->assertDontSee('Luar Kota FullBoard');
    }
}
