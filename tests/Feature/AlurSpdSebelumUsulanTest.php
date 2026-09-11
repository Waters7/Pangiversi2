<?php

namespace Tests\Feature;

use App\Models\KategoriPerjadin;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Surat Perjalanan Dinas adalah dasar penugasan, jadi usulan perjalanan
 * dinas baru boleh diajukan setelah SPD-nya terbit.
 */
class AlurSpdSebelumUsulanTest extends TestCase
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

    // ── Syarat SPD ──

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

    public function test_formulir_usulan_tertutup_sebelum_spd_dibuat(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertRedirect(route('spd.create'))
            ->assertSessionHas('error');
    }

    public function test_pesan_pengalihan_menjelaskan_alasannya(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'Surat Perjalanan Dinas'));
    }

    public function test_formulir_usulan_terbuka_setelah_spd_ada(): void
    {
        $this->buatkanSpd();

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Buat Usulan Perjalanan Dinas');
    }

    /**
     * Penjagaan tidak boleh hanya di formulir: tanpa penjagaan pada
     * penyimpanan, usulan masih bisa dikirim langsung ke alamatnya.
     */
    public function test_penyimpanan_usulan_ikut_dijaga(): void
    {
        $this->actingAs($this->pengguna)
            ->post(route('usulan.store'), ['lokasi' => 'Jakarta'])
            ->assertRedirect(route('spd.create'));

        $this->assertDatabaseCount('usulan', 0);
    }

    public function test_spd_yang_mencantumkan_pengguna_sebagai_pelaksana_ikut_dihitung(): void
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

        $this->actingAs($this->pengguna)->get(route('usulan.create'))->assertOk();
    }

    public function test_spd_milik_orang_lain_tidak_membuka_formulir(): void
    {
        $this->buatkanSpd(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]));

        $this->actingAs($this->pengguna)
            ->get(route('usulan.create'))
            ->assertRedirect(route('spd.create'));
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

    public function test_aksi_cepat_mengunci_usulan_sebelum_spd_ada(): void
    {
        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Terbuka setelah SPD dibuat');
    }

    public function test_aksi_cepat_membuka_usulan_setelah_spd_ada(): void
    {
        $this->buatkanSpd();

        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buat Usulan Perjadin')
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
