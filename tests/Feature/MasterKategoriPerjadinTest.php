<?php

namespace Tests\Feature;

use App\Models\KategoriPerjadin;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori perjalanan dinas kini dikelola lewat Master Data. Sebelumnya
 * daftarnya hanya dapat diubah dari seeder, sehingga menambah satu kategori
 * menuntut penempatan ulang aplikasi.
 */
class MasterKategoriPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function isian(array $ubahan = []): array
    {
        return array_merge([
            'grup' => 'Auditor',
            'nama' => 'Auditor Dalam Kota',
            'kode' => 'AUD-DK',
            'is_aktif' => '1',
        ], $ubahan);
    }

    // ── Halaman ──

    public function test_halaman_terbuka_bagi_super_administrator(): void
    {
        $this->actingAs($this->admin)
            ->get(route('master.kategori-perjadin'))
            ->assertOk()
            ->assertSee('Kategori Perjalanan Dinas')
            ->assertSee('Tambah Kategori');
    }

    public function test_pengguna_biasa_tidak_dapat_membukanya(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('master.kategori-perjadin'))
            ->assertForbidden();
    }

    public function test_menu_master_data_menautkan_halaman_ini(): void
    {
        $this->actingAs($this->admin)
            ->get(route('master.lokasi'))
            ->assertOk()
            ->assertSee(route('master.kategori-perjadin'), false)
            ->assertSee('Kategori Perjadin');
    }

    // ── Tambah ──

    public function test_kategori_baru_dapat_ditambahkan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.kategori-perjadin.store'), $this->isian())
            ->assertRedirect(route('master.kategori-perjadin'));

        $this->assertDatabaseHas('kategori_perjadin', [
            'grup' => 'Auditor',
            'nama' => 'Auditor Dalam Kota',
            'kode' => 'AUD-DK',
            'is_aktif' => true,
        ]);
    }

    public function test_kategori_baru_langsung_ditawarkan_pada_formulir_usulan(): void
    {
        $this->actingAs($this->admin)->post(route('master.kategori-perjadin.store'), $this->isian());

        $pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->terbitkanSpd($pengusul);

        $this->actingAs($pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('<optgroup label="Auditor">', false)
            ->assertSee('Auditor Dalam Kota');
    }

    public function test_kode_tidak_boleh_kembar(): void
    {
        KategoriPerjadin::factory()->create(['kode' => 'AUD-DK']);

        $this->actingAs($this->admin)
            ->post(route('master.kategori-perjadin.store'), $this->isian())
            ->assertSessionHasErrors('kode');
    }

    public function test_grup_di_luar_daftar_resmi_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.kategori-perjadin.store'), $this->isian(['grup' => 'Grup Karangan']))
            ->assertSessionHasErrors('grup');
    }

    public function test_urutan_diisi_sendiri_bila_dikosongkan(): void
    {
        KategoriPerjadin::factory()->create(['grup' => 'Auditor', 'urutan' => 4]);

        $this->actingAs($this->admin)->post(route('master.kategori-perjadin.store'), $this->isian());

        $this->assertSame(5, KategoriPerjadin::firstWhere('kode', 'AUD-DK')->urutan);
    }

    // ── Ubah ──

    public function test_kategori_dapat_diubah(): void
    {
        $kategori = KategoriPerjadin::factory()->create(['nama' => 'Nama Lama', 'kode' => 'LAMA']);

        $this->actingAs($this->admin)
            ->put(route('master.kategori-perjadin.update', $kategori), $this->isian([
                'nama' => 'Nama Baru',
                'kode' => 'LAMA',
            ]))
            ->assertRedirect(route('master.kategori-perjadin'));

        $this->assertSame('Nama Baru', $kategori->fresh()->nama);
    }

    public function test_kode_miliknya_sendiri_tidak_dianggap_kembar(): void
    {
        $kategori = KategoriPerjadin::factory()->create(['kode' => 'AUD-DK']);

        $this->actingAs($this->admin)
            ->put(route('master.kategori-perjadin.update', $kategori), $this->isian())
            ->assertSessionHasNoErrors();
    }

    public function test_kategori_nonaktif_berhenti_ditawarkan(): void
    {
        $kategori = KategoriPerjadin::factory()->create([
            'grup' => 'Auditor',
            'nama' => 'Auditor Luar Kota',
            'is_aktif' => true,
        ]);

        $this->actingAs($this->admin)->put(route('master.kategori-perjadin.update', $kategori), [
            'grup' => $kategori->grup,
            'nama' => $kategori->nama,
            'kode' => $kategori->kode,
        ]);

        $this->assertFalse($kategori->fresh()->is_aktif);
        $this->assertFalse(KategoriPerjadin::terkelompok()->flatten()->contains('id', $kategori->id));
    }

    // ── Hapus ──

    public function test_kategori_yang_belum_dipakai_dapat_dihapus(): void
    {
        $kategori = KategoriPerjadin::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('master.kategori-perjadin.destroy', $kategori))
            ->assertRedirect(route('master.kategori-perjadin'));

        $this->assertDatabaseMissing('kategori_perjadin', ['id' => $kategori->id]);
    }

    /**
     * Menghapus kategori yang terpakai akan membuat usulan lama kehilangan
     * kelas biayanya, jadi yang ditawarkan adalah menonaktifkannya.
     */
    public function test_kategori_yang_masih_dipakai_tidak_dapat_dihapus(): void
    {
        $kategori = KategoriPerjadin::factory()->create();
        Usulan::factory()->create(['id_kategori_perjadin' => $kategori->id]);

        $this->actingAs($this->admin)
            ->delete(route('master.kategori-perjadin.destroy', $kategori))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('kategori_perjadin', ['id' => $kategori->id]);
    }
}
