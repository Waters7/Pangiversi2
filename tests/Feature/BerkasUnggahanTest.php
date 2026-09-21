<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Berkas unggahan disajikan lewat aplikasi, bukan tautan simbolik
 * public/storage yang di hosting bersama kerap dijawab 404.
 */
class BerkasUnggahanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put('dokumen/surat-tugas/st-uji.pdf', '%PDF-1.4 uji');
    }

    public function test_pengguna_yang_masuk_dapat_membuka_berkas_unggahan(): void
    {
        $balasan = $this->actingAs(User::factory()->create())
            ->get(route('berkas.lihat', 'dokumen/surat-tugas/st-uji.pdf'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="st-uji.pdf"');

        $this->assertSame('%PDF-1.4 uji', $balasan->getFile()->getContent());
    }

    public function test_tautan_berkas_mengarah_ke_rute_aplikasi_bukan_storage(): void
    {
        $this->assertSame(url('/berkas/dokumen/surat-tugas/st-uji.pdf'), route('berkas.lihat', 'dokumen/surat-tugas/st-uji.pdf'));

        Storage::disk('public')->put('foto-profil/a.jpg', 'gambar');
        $pengguna = User::factory()->create(['foto' => 'foto-profil/a.jpg']);

        $this->assertSame(route('berkas.lihat', 'foto-profil/a.jpg'), $pengguna->url_foto);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('berkas.lihat', 'dokumen/surat-tugas/st-uji.pdf'))->assertRedirect(route('login'));
    }

    public function test_jalur_di_luar_folder_unggahan_atau_yang_tidak_ada_dijawab_404(): void
    {
        $pengguna = User::factory()->create();

        $this->actingAs($pengguna)->get('/berkas/dokumen/surat-tugas/tidak-ada.pdf')->assertNotFound();
        $this->actingAs($pengguna)->get('/berkas/../.env')->assertNotFound();
        $this->actingAs($pengguna)->get('/berkas/dokumen/../../.env')->assertNotFound();
        $this->actingAs($pengguna)->get('/berkas/rahasia/x.pdf')->assertNotFound();
    }
}
