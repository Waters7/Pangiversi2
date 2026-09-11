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
 * Usulan perjadin menunjuk SPD yang mendasarinya, dan kolom yang sudah
 * ditulis saat membuat SPD tidak diketik ulang pada formulir usulan.
 */
class SpdDasarUsulanTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private SuratPerjalananDinas $spd;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->spd = $this->terbitkanSpd($this->pengusul);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function dataUsulan(array $ubahan = []): array
    {
        return array_merge([
            'id_spd' => $this->spd->id,
            'id_kegiatan' => Kegiatan::create(['nama' => 'Rapat Koordinasi'])->id,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create()->id,
            'no_tugas' => 'KP.01.02/F.XXX/1557/2026',
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-07',
            'surat_tugas' => UploadedFile::fake()->create('st.pdf', 100, 'application/pdf'),
            'no_spd' => 'KU.02.04/F.XXX.8/1234/2026',
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
        ], $ubahan);
    }

    // ── Formulir ──

    public function test_formulir_menawarkan_spd_milik_pengusul(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Dasar Penugasan')
            ->assertSee('name="id_spd"', false)
            ->assertViewHas('spdTerkait', fn (array $daftar) => count($daftar) === 1
                && $daftar[0]['id'] === $this->spd->id);
    }

    public function test_isi_spd_dibawa_ke_formulir_agar_tidak_diketik_ulang(): void
    {
        $bekal = $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->viewData('spdTerkait')[0];

        // Kolom yang sudah terisi saat membuat SPD ikut dibawa, supaya
        // tanggal pada usulan tidak berbeda dari tanggal pada SPD.
        $this->assertSame($this->spd->tempat_tujuan, $bekal['tempat_tujuan']);
        $this->assertSame($this->spd->tempat_berangkat, $bekal['tempat_berangkat']);
        $this->assertSame($this->spd->tanggal_berangkat->toDateString(), $bekal['tanggal_berangkat']);
        $this->assertSame($this->spd->tanggal_kembali->toDateString(), $bekal['tanggal_kembali']);
        $this->assertSame($this->spd->maksud, $bekal['maksud']);
        $this->assertSame($this->spd->alat_angkut, $bekal['alat_angkut']);
        $this->assertSame($this->spd->lama_hari, $bekal['lama_hari']);
    }

    public function test_spd_pegawai_lain_tidak_ditawarkan(): void
    {
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->terbitkanSpd($orangLain);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertViewHas('spdTerkait', fn (array $daftar) => count($daftar) === 1);
    }

    public function test_rekan_sepelaksana_disiapkan_sebagai_rombongan(): void
    {
        $rekan = User::factory()->create(['nama' => 'Rekan Sepelaksana']);

        $this->spd->pelaksana()->create([
            'urutan' => 2,
            'id_user' => $rekan->id,
            'nomor_surat' => 'KU.02.04/F.XXX.8/2/2026',
            'nama' => $rekan->nama,
            'nip' => $rekan->nip,
        ]);

        $bekal = $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->viewData('spdTerkait')[0];

        $this->assertSame([['id' => $rekan->id, 'nama' => $rekan->nama]], $bekal['rekan']);
    }

    // ── Penyimpanan ──

    public function test_usulan_tersimpan_terkait_dengan_spd_yang_dipilih(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan());

        $this->assertSame($this->spd->id, Usulan::first()->id_spd);
        $this->assertTrue(Usulan::first()->spd->is($this->spd));
    }

    public function test_usulan_tanpa_spd_ditolak(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_spd' => null]))
            ->assertSessionHasErrors('id_spd');

        $this->assertSame(0, Usulan::count());
    }

    /**
     * Pengecekan tidak cukup "SPD itu ada": tanpa memastikan pemiliknya,
     * siapa pun dapat menempelkan usulannya pada SPD orang lain.
     */
    public function test_spd_milik_orang_lain_ditolak(): void
    {
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $spdOrangLain = $this->terbitkanSpd($orangLain);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_spd' => $spdOrangLain->id]))
            ->assertSessionHasErrors('id_spd');

        $this->assertSame(0, Usulan::count());
    }

    /**
     * Rekan sepelaksana pada SPD yang sama mengajukan usulannya sendiri dengan
     * SPD bertanda tangannya; kiriman "anggota" tidak lagi membuatkan usulan.
     */
    public function test_rekan_pada_spd_yang_sama_tidak_dibuatkan_usulan(): void
    {
        $rekan = User::factory()->create();

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        $this->assertSame(1, Usulan::count());
        $this->assertSame($this->pengusul->id, Usulan::first()->id_user);
        $this->assertSame($this->spd->id, Usulan::first()->id_spd);
    }

    public function test_menghapus_spd_tidak_ikut_menghapus_usulannya(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $this->spd->pelaksana()->delete();
        $this->spd->delete();

        // Pertanggungjawaban perjalanan tetap harus ada meski suratnya dicabut.
        $this->assertSame(1, Usulan::count());
        $this->assertNull(Usulan::first()->id_spd);
    }

    // ── Jenis kegiatan ──

    public function test_jenis_kegiatan_wajib_dipilih(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_kegiatan' => null]))
            ->assertSessionHasErrors('id_kegiatan');

        $this->assertSame(0, Usulan::count());
    }

    public function test_jenis_kegiatan_tersimpan_pada_usulan(): void
    {
        $kegiatan = Kegiatan::create(['nama' => 'Monitoring dan Evaluasi']);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['id_kegiatan' => $kegiatan->id]));

        $this->assertSame($kegiatan->id, Usulan::first()->id_kegiatan);
    }

    public function test_formulir_ubah_juga_menawarkan_jenis_kegiatan(): void
    {
        $kegiatan = Kegiatan::create(['nama' => 'Pelatihan Teknis']);
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => 'draft',
            'id_kegiatan' => $kegiatan->id,
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.edit', $usulan))
            ->assertOk()
            ->assertSee('name="id_kegiatan"', false)
            ->assertSee('Pelatihan Teknis');
    }
}
