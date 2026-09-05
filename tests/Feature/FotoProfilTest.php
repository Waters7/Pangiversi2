<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Foto profil menggantikan avatar inisial bila pengguna mengunggahnya.
 */
class FotoProfilTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pengguna = User::factory()->create(['nama' => 'Octavianus Elricth Modami']);
    }

    private function unggah(int $lebar = 800, int $tinggi = 600): TestResponse
    {
        return $this->actingAs($this->pengguna)
            ->post(route('profil.foto'), [
                'foto' => UploadedFile::fake()->image('potret.jpg', $lebar, $tinggi),
            ]);
    }

    // ── Unggah ──

    public function test_foto_tersimpan_dan_tercatat_pada_pengguna(): void
    {
        $this->unggah()->assertSessionHas('success');

        $pengguna = $this->pengguna->fresh();

        $this->assertNotNull($pengguna->foto);
        $this->assertStringStartsWith('foto-profil/', $pengguna->foto);
        Storage::disk('public')->assertExists($pengguna->foto);
    }

    public function test_foto_dipangkas_menjadi_bujur_sangkar(): void
    {
        $this->unggah(1200, 700);

        $isi = Storage::disk('public')->get($this->pengguna->fresh()->foto);
        [$lebar, $tinggi] = getimagesizefromstring($isi);

        $this->assertSame($lebar, $tinggi, 'Foto profil harus bujur sangkar.');
        $this->assertSame(512, $lebar);
    }

    public function test_foto_lama_dihapus_saat_diganti(): void
    {
        $this->unggah();
        $lama = $this->pengguna->fresh()->foto;

        $this->unggah();
        $baru = $this->pengguna->fresh()->foto;

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('profil.foto'), [
                'foto' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertNull($this->pengguna->fresh()->foto);
    }

    public function test_unggahan_wajib_menyertakan_berkas(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('profil.foto'), [])
            ->assertSessionHasErrors('foto');
    }

    // ── Hapus ──

    public function test_foto_dapat_dihapus(): void
    {
        $this->unggah();
        $jalur = $this->pengguna->fresh()->foto;

        $this->actingAs($this->pengguna)
            ->delete(route('profil.foto.hapus'))
            ->assertSessionHas('success');

        $this->assertNull($this->pengguna->fresh()->foto);
        Storage::disk('public')->assertMissing($jalur);
    }

    public function test_menghapus_tanpa_foto_memberi_keterangan(): void
    {
        $this->actingAs($this->pengguna)
            ->delete(route('profil.foto.hapus'))
            ->assertSessionHas('error');
    }

    // ── Tampilan ──

    public function test_avatar_memakai_inisial_selama_belum_ada_foto(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('>OE</span>', false);
    }

    public function test_avatar_memakai_foto_setelah_diunggah(): void
    {
        $this->unggah();

        $this->actingAs($this->pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee($this->pengguna->fresh()->url_foto, false)
            ->assertDontSee('>OE</span>', false);
    }

    public function test_foto_ikut_tampil_pada_panel_akun_di_topbar(): void
    {
        $this->unggah();

        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($this->pengguna->fresh()->url_foto, false);
    }

    public function test_tombol_hapus_hanya_muncul_bila_sudah_ada_foto(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertDontSee('Hapus Foto');

        $this->unggah();

        $this->actingAs($this->pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('Hapus Foto');
    }

    public function test_berkas_foto_yang_hilang_kembali_ke_inisial(): void
    {
        $this->unggah();
        Storage::disk('public')->delete($this->pengguna->fresh()->foto);

        $this->assertNull($this->pengguna->fresh()->url_foto);
        $this->assertFalse($this->pengguna->fresh()->punyaFoto());
    }

    public function test_tamu_tidak_dapat_mengunggah_foto(): void
    {
        $this->post(route('profil.foto'), [
            'foto' => UploadedFile::fake()->image('potret.jpg'),
        ])->assertRedirect(route('login'));
    }
}
