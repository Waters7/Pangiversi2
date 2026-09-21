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
 * SPD yang dibuat lewat aplikasi hanya membantu mengisi usulan — bukan
 * syarat. Usulan boleh diajukan tanpa SPD dari aplikasi; dasar
 * penugasannya adalah SPD bertanda tangan yang diunggah bersama usulan.
 */
class UsulanTanpaSpdAplikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        KategoriPerjadin::factory()->create();
    }

    private function buatkanSpd(?User $untuk = null): SuratPerjalananDinas
    {
        $spd = SuratPerjalananDinas::create([
            'id_pembuat' => ($untuk ?? $this->pengguna)->id,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => today(),
            'maksud' => 'Rapat koordinasi.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => today()->addDays(7),
            'tanggal_kembali' => today()->addDays(9),
            'lama_hari' => 3,
        ]);

        $spd->pelaksana()->create([
            'urutan' => 1,
            'id_user' => ($untuk ?? $this->pengguna)->id,
            'nomor_surat' => 'KU.02.04/F.XXX.8/1/2026',
            'nama' => ($untuk ?? $this->pengguna)->nama,
            'nip' => ($untuk ?? $this->pengguna)->nip,
        ]);

        return $spd;
    }

    // ── SPD dari aplikasi tidak wajib ──

    /**
     * Usulan yang diajukan menggerakkan SPD, biaya, dan pembayaran, jadi
     * pengisiannya ditanya ulang sekali sebelum benar-benar terkirim.
     */
    public function test_formulir_menanyakan_ulang_sebelum_mengajukan(): void
    {
        $this->buatkanSpd();

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Kirim pengajuan perjalanan dinas?')
            ->assertSee('Apakah Anda sudah benar mengisi seluruh datanya?')
            ->assertSee('Periksa Lagi')
            ->assertSee('Ya, Kirim');
    }

    public function test_formulir_usulan_terbuka_tanpa_spd_dari_aplikasi(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Buat Usulan Perjalanan Dinas')
            ->assertSee('Belum ada SPD yang dibuat lewat aplikasi atas nama Anda')
            ->assertSee('Tanpa SPD dari aplikasi, isi data perjalanan sendiri');
    }

    public function test_formulir_menawarkan_spd_dari_aplikasi_sebagai_pengisi_otomatis(): void
    {
        $this->buatkanSpd();

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('KU.02.04/F.XXX.8/1/2026')
            ->assertSee('(opsional)')
            ->assertSee('Usulan tetap');
    }

    /**
     * Usulan tersimpan tanpa id_spd; yang wajib tetap SPD bertanda tangan
     * beserta nomornya.
     */
    public function test_usulan_dapat_diajukan_tanpa_spd_dari_aplikasi(): void
    {
        Storage::fake('public');
        Kegiatan::factory()->create();

        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), $this->isianUsulan())
            ->assertRedirect(route('usulan.list'))
            ->assertSessionHas('success');

        $usulan = Usulan::sole();
        $this->assertNull($usulan->id_spd);
        $this->assertSame('AR.05.02/F.XXX/99/2026', $usulan->no_spd);
        $this->assertTrue($usulan->punyaSpdBertandaTangan());
    }

    public function test_spd_bertanda_tangan_dan_nomornya_tetap_wajib(): void
    {
        Storage::fake('public');
        Kegiatan::factory()->create();

        $this->actingAs($this->pengguna)
            ->from(route('usulan.create'))
            ->post(route('usulan.store'), array_diff_key($this->isianUsulan(), array_flip(['no_spd', 'spd_ditandatangani'])))
            ->assertRedirect(route('usulan.create'))
            ->assertSessionHasErrors(['no_spd', 'spd_ditandatangani']);

        $this->assertDatabaseCount('usulan', 0);
    }

    /** @return array<string, mixed> */
    private function isianUsulan(): array
    {
        return [
            'no_spd' => 'AR.05.02/F.XXX/99/2026',
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 120, 'application/pdf'),
            'id_kegiatan' => Kegiatan::first()->id,
            'id_kategori_perjadin' => KategoriPerjadin::first()->id,
            'no_tugas' => 'KP.01.02/F.XXX/99/2026',
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'tanggal_mulai' => today()->addDays(7)->toDateString(),
            'tanggal_selesai' => today()->addDays(9)->toDateString(),
            'uraian' => 'Rapat koordinasi.',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 120, 'application/pdf'),
        ];
    }

    public function test_spd_yang_mencantumkan_pengguna_sebagai_pelaksana_ikut_ditawarkan(): void
    {
        $penyusun = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $spd = $this->buatkanSpd($penyusun);
        $spd->pelaksana()->create([
            'urutan' => 2,
            'id_user' => $this->pengguna->id,
            'nomor_surat' => 'KU.02.04/F.XXX.8/2/2026',
            'nama' => $this->pengguna->nama,
            'nip' => $this->pengguna->nip,
        ]);

        // Yang ditawarkan adalah nomor SPD pengguna ini sendiri, bukan nomor orang pertama.
        $halaman = $this->actingAs($this->pengguna)->get(route('usulan.create'))->assertOk()
            ->assertSee('KU.02.04/F.XXX.8/2/2026')
            ->assertDontSee('KU.02.04/F.XXX.8/1/2026');
        $this->assertSame('KU.02.04/F.XXX.8/2/2026', $halaman->viewData('spdTerkait')[0]['nomor']);
    }

    /** SPD yang dibuatkan untuk orang lain — pembuatnya bukan pelaksana — bukan miliknya untuk diusulkan. */
    public function test_spd_yang_dibuat_untuk_orang_lain_tidak_ditawarkan_kepada_pembuatnya(): void
    {
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $spd = $this->buatkanSpd($orangLain);
        $spd->update(['id_pembuat' => $this->pengguna->id]);

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertDontSee('KU.02.04/F.XXX.8/1/2026');
        $this->assertSame([], $this->actingAs($this->pengguna)->get(route('usulan.create'))->viewData('spdTerkait'));
    }

    public function test_spd_milik_orang_lain_tidak_dapat_dipilih(): void
    {
        Storage::fake('public');
        Kegiatan::factory()->create();
        $spdOrangLain = $this->buatkanSpd(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]));

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertDontSee('KU.02.04/F.XXX.8/1/2026');

        $this->actingAs($this->pengguna)
            ->from(route('usulan.create'))
            ->post(route('usulan.store'), $this->isianUsulan() + ['id_spd' => $spdOrangLain->id])
            ->assertSessionHasErrors('id_spd');
    }

    // ── Dashboard ──

    public function test_dashboard_menampilkan_ringkasan_spd(): void
    {
        $this->buatkanSpd();

        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Surat Perjalanan Dinas')
            ->assertSee('KU.02.04/F.XXX.8/1/2026')
            ->assertSee(route('spd.index'), false);
    }

    public function test_dashboard_mengajak_membuat_spd_bila_belum_ada(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Belum ada SPD')
            ->assertSee('Buat SPD Pertama');
    }

    public function test_aksi_cepat_usulan_selalu_terbuka(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buat Usulan Perjadin')
            ->assertSee(route('usulan.create'), false)
            ->assertDontSee('Terbuka setelah SPD dibuat');
    }

    // ── Menu ──

    /**
     * Dibandingkan lewat label menunya, bukan komentar Blade: komentar
     * tidak ikut terender sehingga tidak dapat dijadikan penanda.
     */
    public function test_menu_spd_berada_di_atas_usulan_perjadin(): void
    {
        $isi = $this->actingAs($this->pengguna)->get(route('dashboard'))->assertOk()->getContent();

        // Kemunculan pertama tiap label ada pada panel samping.
        $posisiSpd = strpos($isi, 'Pembuatan SPD');
        $posisiUsulan = strpos($isi, 'Daftar Usulan Perjadin');

        $this->assertNotFalse($posisiSpd);
        $this->assertNotFalse($posisiUsulan);
        $this->assertLessThan($posisiUsulan, $posisiSpd, 'Menu SPD harus berada di atas menu usulan perjadin.');
    }

    public function test_menu_usulan_bernama_usulan_perjadin(): void
    {
        $isi = $this->actingAs($this->pengguna)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Usulan Perjadin', $isi);
        $this->assertStringContainsString('Daftar Usulan Perjadin', $isi);
        $this->assertStringContainsString('Buat Usulan Perjadin', $isi);
    }
}
