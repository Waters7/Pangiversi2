<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SPD berangkutan udara atau laut selalu perjalanan luar kota, dan usulan
 * yang terkirim dua kali dapat dihapus sendiri oleh pelaksananya.
 */
class UsulanAngkutanDanDuplikatTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->seed(KategoriPerjadinSeeder::class);
        Kegiatan::firstOrCreate(['nama' => 'Mengikuti rapat, seminar, lokakarya, atau studi banding']);
    }

    /**
     * @param  array<string, mixed>  $ubah
     */
    private function ajukan(string $alatAngkut, JenisPerjadin $jenis, string $kodeKategori, array $ubah = []): TestResponse
    {
        $spd = $this->terbitkanSpd($this->pelaksana);
        $spd->update(['alat_angkut' => $alatAngkut]);

        return $this->actingAs($this->pelaksana)
            ->from(route('usulan.create', ['jenis' => $jenis->value]))
            ->post(route('usulan.store'), array_merge([
                'jenis' => $jenis->value,
                'id_spd' => $spd->id,
                'id_kategori_perjadin' => KategoriPerjadin::where('kode', $kodeKategori)->firstOrFail()->id,
                'id_kegiatan' => Kegiatan::first()->id,
                'no_spd' => 'AR.05.02/F.XXX/'.fake()->unique()->numberBetween(100, 999).'/2026',
                'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
                'no_tugas' => 'KP.01.02/F.XXX/77/2026',
                'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
                'lokasi' => 'Jakarta',
                'instansi' => 'Kementerian Kesehatan',
                'tanggal_mulai' => today()->addDays(7)->toDateString(),
                'tanggal_selesai' => today()->addDays(9)->toDateString(),
            ], $ubah));
    }

    // ── SPD udara atau laut pasti luar kota ──

    public function test_spd_angkutan_udara_atau_laut_menolak_kategori_dalam_kota(): void
    {
        foreach (['Angkutan Udara', 'Angkutan Laut'] as $alat) {
            $this->ajukan($alat, JenisPerjadin::DalamKota, 'DK-FB')
                ->assertSessionHasErrors(['id_kategori_perjadin' => "SPD yang dipilih memakai {$alat} — perjalanan dengan angkutan udara atau laut adalah perjalanan luar kota, jadi kategorinya tidak boleh dalam kota. Pilih kategori luar kota, atau pilih SPD lain bila SPD ini keliru."]);
        }

        // Supervisi pun demikian: SPD udara hanya cocok dengan supervisi luar kota.
        $this->ajukan('Angkutan Udara', JenisPerjadin::Supervisi, 'SV-DK')->assertSessionHasErrors('id_kategori_perjadin');

        $this->assertDatabaseCount('usulan', 0);
    }

    public function test_spd_angkutan_udara_diterima_dengan_kategori_luar_kota(): void
    {
        $this->ajukan('Angkutan Udara', JenisPerjadin::LuarKota, 'LK-NFB')->assertSessionDoesntHaveErrors('id_kategori_perjadin');
        $this->ajukan('Angkutan Darat', JenisPerjadin::DalamKota, 'DK-FB')->assertSessionDoesntHaveErrors('id_kategori_perjadin');
    }

    public function test_formulir_menandai_spd_udara_dan_menutup_kategori_dalam_kota(): void
    {
        $this->terbitkanSpd($this->pelaksana);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.create', ['jenis' => JenisPerjadin::Supervisi->value]))
            ->assertOk()
            ->assertSee('udara_laut\u0022:true', false)
            ->assertSee('x-bind:disabled="spdUdaraLaut"', false)
            ->assertSee('kategori dalam kota tidak dapat dipilih');
    }

    // ── Usulan duplikat ──

    private function usulan(array $ubah = []): Usulan
    {
        return Usulan::factory()->create(array_merge([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::MenungguPpk->value,
            'no_tugas' => 'KP.01.02/F.XXX/77/2026',
            'tanggal_mulai' => '2026-10-20',
            'tanggal_selesai' => '2026-10-22',
        ], $ubah));
    }

    public function test_pelaksana_dapat_menghapus_usulan_kembar_yang_sudah_diajukan(): void
    {
        $asli = $this->usulan();
        $kembar = $this->usulan();

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.list'))
            ->assertSee('Duplikat dari '.$asli->no_usulan);

        $this->actingAs($this->pelaksana)
            ->delete(route('usulan.destroy', $kembar))
            ->assertSessionHas('success', "Usulan {$kembar->no_usulan} dihapus sebagai duplikat — usulan {$asli->no_usulan} tetap berjalan.");

        $this->assertModelMissing($kembar);

        // Yang tersisa tidak lagi berkembar, jadi tidak dapat dihapus selama diajukan.
        $this->actingAs($this->pelaksana)->delete(route('usulan.destroy', $asli))->assertSessionHas('error');
        $this->assertModelExists($asli);
    }

    public function test_usulan_berbeda_atau_sudah_dibayar_tidak_dihapus_sebagai_duplikat(): void
    {
        $this->usulan();
        $lain = $this->usulan(['tanggal_mulai' => '2026-11-02', 'tanggal_selesai' => '2026-11-03']);
        $this->actingAs($this->pelaksana)->delete(route('usulan.destroy', $lain))->assertSessionHas('error');
        $this->assertModelExists($lain);

        $dibayar = $this->usulan(['status' => StatusUsulan::Disetujui->value]);
        Keuangan::factory()->create(['id_usulan' => $dibayar->id, 'tanggal_transfer' => today()]);
        $this->actingAs($this->pelaksana)->delete(route('usulan.destroy', $dibayar))->assertSessionHas('error');
        $this->assertModelExists($dibayar);

        // Kembaran milik orang lain pun tidak dapat dihapus pelaksana ini.
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->actingAs($orangLain)->delete(route('usulan.destroy', $dibayar))->assertForbidden();
    }
}
