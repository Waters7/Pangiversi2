<?php

namespace Tests\Feature;

use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Surat tugas dilampirkan sejak SPD dibuat, lalu ikut ke usulan perjadin
 * yang mengacu pada SPD itu — berkas dan nomornya tidak diminta lagi.
 * Akun pembebanan tidak lagi diisi pembuat SPD; PPK yang mengisinya saat
 * verifikasi.
 */
class SuratTugasPadaSpdTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->pengguna = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        Kegiatan::create(['nama' => 'Rapat Koordinasi']);
        KategoriPerjadin::factory()->create();
    }

    /** @return array<string, mixed> */
    private function isianSpd(array $ubah = []): array
    {
        return array_replace_recursive([
            'dikeluarkan_di' => 'Manado',
            'pelaksana' => [[
                'nomor_surat' => '123',
                'id_user' => $this->pengguna->id,
                'nama' => $this->pengguna->nama,
                'nip' => $this->pengguna->nip,
            ]],
            'maksud' => 'Konsultasi teknis.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => '2026-10-10',
            'tanggal_kembali' => '2026-10-12',
            'instansi_pembebanan' => 'Politeknik Kesehatan Kemenkes Manado',
            'no_tugas' => 'KP.01.02/F.XXX/1557/2026',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 120, 'application/pdf'),
        ], $ubah);
    }

    /** @return array<string, mixed> */
    private function isianUsulan(array $ubah = []): array
    {
        return array_merge([
            'id_kegiatan' => Kegiatan::first()->id,
            'id_kategori_perjadin' => KategoriPerjadin::first()->id,
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-12',
            'no_spd' => 'AR.05.02/F.XXX/2372/2026',
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
        ], $ubah);
    }

    // ── Formulir SPD ──

    public function test_spd_menyimpan_nomor_dan_berkas_surat_tugas(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('spd.store'), $this->isianSpd())
            ->assertSessionHasNoErrors();

        $spd = SuratPerjalananDinas::sole();
        $this->assertSame('KP.01.02/F.XXX/1557/2026', $spd->no_tugas);
        $this->assertTrue($spd->punyaSuratTugas());
        Storage::disk('public')->assertExists($spd->surat_tugas);

        $this->actingAs($this->pengguna)
            ->get(route('spd.show', $spd))
            ->assertOk()
            ->assertSee('KP.01.02/F.XXX/1557/2026')
            ->assertSee(route('berkas.lihat', $spd->surat_tugas));
    }

    public function test_surat_tugas_pada_spd_tidak_wajib_dan_dipertahankan_saat_disunting(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('spd.store'), $this->isianSpd(['no_tugas' => '', 'surat_tugas' => null]))
            ->assertSessionHasNoErrors();

        $spd = SuratPerjalananDinas::sole();
        $this->assertNull($spd->no_tugas);
        $this->assertFalse($spd->punyaSuratTugas());

        $this->actingAs($this->pengguna)->put(route('spd.update', $spd), $this->isianSpd());
        $lama = $spd->fresh()->surat_tugas;
        $this->assertNotNull($lama);

        // Menyunting tanpa unggahan baru tidak menghapus berkas yang ada.
        $this->actingAs($this->pengguna)->put(route('spd.update', $spd), $this->isianSpd(['surat_tugas' => null, 'maksud' => 'Diubah.']));
        $this->assertSame($lama, $spd->fresh()->surat_tugas);
        $this->assertSame('Diubah.', $spd->fresh()->maksud);
    }

    public function test_akun_pembebanan_tidak_lagi_diisi_dari_formulir_spd(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertDontSee('name="akun_pembebanan"', false)
            ->assertSee('Akun pembebanan diisi PPK');

        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isianSpd(['akun_pembebanan' => '524111']));

        $this->assertNull(SuratPerjalananDinas::sole()->akun_pembebanan);
    }

    // ── Sinkron ke usulan ──

    public function test_usulan_mengambil_surat_tugas_dan_nomornya_dari_spd_terpilih(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isianSpd());
        $spd = SuratPerjalananDinas::sole();

        $formulir = $this->actingAs($this->pengguna)->get(route('usulan.create'))->assertOk();
        $bekal = $formulir->viewData('spdTerkait')[0];
        $this->assertSame('KP.01.02/F.XXX/1557/2026', $bekal['no_tugas']);
        $this->assertSame(route('berkas.lihat', $spd->surat_tugas), $bekal['surat_tugas']);

        // Tanpa berkas dan tanpa nomor surat tugas: keduanya diambil dari SPD.
        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), $this->isianUsulan(['id_spd' => $spd->id]))
            ->assertSessionHasNoErrors();

        $usulan = Usulan::sole();
        $this->assertSame('KP.01.02/F.XXX/1557/2026', $usulan->no_tugas);

        $dokumen = $usulan->dokumen()->first();
        $this->assertNotNull($dokumen->surat_tugas);
        // Disalin, bukan berbagi berkas dengan SPD.
        $this->assertNotSame($spd->surat_tugas, $dokumen->surat_tugas);
        Storage::disk('public')->assertExists($dokumen->surat_tugas);
    }

    public function test_tanpa_spd_bersurat_tugas_unggahan_dan_nomornya_tetap_wajib(): void
    {
        $this->actingAs($this->pengguna)->post(route('spd.store'), $this->isianSpd(['no_tugas' => '', 'surat_tugas' => null]));
        $spd = SuratPerjalananDinas::sole();

        $this->actingAs($this->pengguna)
            ->from(route('usulan.create'))
            ->post(route('usulan.store'), $this->isianUsulan(['id_spd' => $spd->id]))
            ->assertSessionHasErrors(['surat_tugas', 'no_tugas']);

        $this->actingAs($this->pengguna)
            ->from(route('usulan.create'))
            ->post(route('usulan.store'), $this->isianUsulan())
            ->assertSessionHasErrors(['surat_tugas', 'no_tugas']);

        $this->assertSame(0, Usulan::count());
    }
}
