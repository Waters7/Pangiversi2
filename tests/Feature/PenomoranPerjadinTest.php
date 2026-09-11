<?php

namespace Tests\Feature;

use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenomoranPerjadin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nomor perjadin dibentuk PJ-KodeUnit-Tahun-BulanUsulan-Urut, dan nomor surat
 * tugas mengikuti klasifikasi arsip Kemenkes.
 */
class PenomoranPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private PenomoranPerjadin $penomoran;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->penomoran = app(PenomoranPerjadin::class);
    }

    private function pegawai(string $kodeUnit = 'KEP'): User
    {
        $pegawai = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'id_unit' => UnitKerja::factory()->create(['kode' => $kodeUnit])->id,
        ]);

        // Usulan perjadin baru terbuka setelah SPD terbit.
        $this->terbitkanSpd($pegawai);

        return $pegawai;
    }

    // ── Nomor perjadin ──

    public function test_nomor_mengikuti_pola_yang_ditetapkan(): void
    {
        $nomor = $this->penomoran->nomorPerjadin($this->pegawai('KEP'), '2026-08-15');

        $this->assertSame('PJ-KEP-'.now()->format('Y').'-08-001', $nomor);
    }

    public function test_bulan_diambil_dari_tanggal_perjalanan(): void
    {
        $pegawai = $this->pegawai('GIZ');

        $this->assertStringContainsString('-03-', $this->penomoran->nomorPerjadin($pegawai, '2026-03-10'));
        $this->assertStringContainsString('-11-', $this->penomoran->nomorPerjadin($pegawai, '2026-11-02'));
    }

    public function test_urutan_dihitung_terpisah_per_unit_kerja(): void
    {
        $kep = $this->pegawai('KEP');
        $giz = $this->pegawai('GIZ');

        Usulan::factory()->create(['no_usulan' => $this->penomoran->nomorPerjadin($kep, '2026-08-01')]);
        Usulan::factory()->create(['no_usulan' => $this->penomoran->nomorPerjadin($kep, '2026-08-01')]);

        $this->assertSame('PJ-KEP-'.now()->format('Y').'-08-003', $this->penomoran->nomorPerjadin($kep, '2026-08-01'));
        $this->assertSame('PJ-GIZ-'.now()->format('Y').'-08-001', $this->penomoran->nomorPerjadin($giz, '2026-08-01'));
    }

    public function test_urutan_terpisah_per_bulan(): void
    {
        $pegawai = $this->pegawai('BID');

        Usulan::factory()->create(['no_usulan' => $this->penomoran->nomorPerjadin($pegawai, '2026-08-01')]);

        $this->assertSame('PJ-BID-'.now()->format('Y').'-09-001', $this->penomoran->nomorPerjadin($pegawai, '2026-09-01'));
    }

    public function test_nomor_tidak_terpakai_ulang_setelah_usulan_dihapus(): void
    {
        $pegawai = $this->pegawai('FAR');

        $satu = Usulan::factory()->create(['no_usulan' => $this->penomoran->nomorPerjadin($pegawai, '2026-08-01')]);
        Usulan::factory()->create(['no_usulan' => $this->penomoran->nomorPerjadin($pegawai, '2026-08-01')]);

        $satu->delete();

        // Urutan lanjut dari nomor tertinggi, bukan dari jumlah baris.
        $this->assertSame('PJ-FAR-'.now()->format('Y').'-08-003', $this->penomoran->nomorPerjadin($pegawai, '2026-08-01'));
    }

    public function test_pegawai_tanpa_unit_memakai_kode_umum(): void
    {
        $tanpaUnit = User::factory()->create(['id_unit' => null]);

        $this->assertStringStartsWith('PJ-UMUM-', $this->penomoran->nomorPerjadin($tanpaUnit, '2026-08-01'));
    }

    public function test_usulan_baru_memakai_nomor_ini(): void
    {
        $pegawai = $this->pegawai('KESLING');
        User::factory()->ppk()->create();

        $this->actingAs($pegawai)->post(route('usulan.store'), [
            'id_spd' => $this->spdMilik($pegawai)->id,
            'id_kegiatan' => Kegiatan::factory()->create()->id,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create()->id,
            'no_tugas' => 'KP.01.02/F.XXX/1/2026',
            'lokasi' => 'Jakarta',
            'instansi' => 'Kemenkes',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-07',
            'surat_tugas' => UploadedFile::fake()->create('st.pdf', 100, 'application/pdf'),
            'no_spd' => 'KU.02.04/F.XXX.8/1234/2026',
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame('PJ-KESLING-'.now()->format('Y').'-10-001', Usulan::first()->no_usulan);
    }

    // ── Formulir usulan ──

    public function test_formulir_menaruh_surat_tugas_paling_atas(): void
    {
        // Diperiksa dengan akun super admin juga, karena sidebar-nya paling padat.
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->terbitkanSpd($admin);

        $isi = $this->actingAs($admin)
            ->get(route('usulan.create'))
            ->assertOk()
            ->getContent();

        // Dibandingkan lewat posisi input-nya, bukan teks labelnya: label
        // "Kategori Perjalanan Dinas" juga muncul pada menu Master Data.
        $posisiBerkas = strpos($isi, 'name="surat_tugas"');
        $posisiNomor = strpos($isi, 'name="no_tugas"');
        $posisiLokasi = strpos($isi, 'name="lokasi"');

        $this->assertNotFalse($posisiBerkas);
        $this->assertNotFalse($posisiNomor);
        $this->assertNotFalse($posisiLokasi);

        // Berkasnya diunggah lebih dulu, baru nomornya disalin dari dokumen itu.
        $this->assertLessThan($posisiNomor, $posisiBerkas, 'Berkas surat tugas harus diminta sebelum nomornya.');
        $this->assertLessThan($posisiLokasi, $posisiNomor, 'Surat tugas harus berada di atas data perjalanan.');
    }

    public function test_formulir_menawarkan_jenis_kegiatan_dari_master_data(): void
    {
        Kegiatan::create(['nama' => 'Rapat Koordinasi Nasional']);

        $this->actingAs($this->pegawai())
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('name="id_kegiatan"', false)
            ->assertSee('Rapat Koordinasi Nasional');
    }

    public function test_nomor_surat_tugas_diisi_manual_sesuai_dokumen(): void
    {
        $this->actingAs($this->pegawai())
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Salin persis seperti tertulis pada surat')
            ->assertSee('cth: KP.01.02/F.XXX/1557/2026');
    }

    public function test_formulir_tidak_lagi_punya_dua_input_surat_tugas(): void
    {
        $isi = $this->actingAs($this->pegawai())
            ->get(route('usulan.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($isi, 'name="surat_tugas"'));
        $this->assertSame(1, substr_count($isi, 'name="no_tugas"'));
    }
}
