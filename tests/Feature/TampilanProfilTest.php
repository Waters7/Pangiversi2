<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel pengguna dan halaman profil dirender sepenuhnya dari aset lokal,
 * sehingga nama pegawai tidak pernah dikirim ke layanan gambar pihak ketiga
 * dan tampilannya tetap utuh saat aplikasi dijalankan di jaringan lokal.
 */
class TampilanProfilTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create([
            'nama' => 'Rahmatullah Pontoh',
            'role' => PeranPengguna::DosenTendik->value,
            ...$atribut,
        ]);
    }

    // ── Avatar lokal ──

    public function test_tidak_ada_permintaan_gambar_ke_layanan_luar(): void
    {
        $pengguna = $this->pengguna();

        foreach ([route('profil.index'), route('dashboard'), route('usulan.list')] as $halaman) {
            $this->actingAs($pengguna)
                ->get($halaman)
                ->assertOk()
                ->assertDontSee('ui-avatars.com');
        }
    }

    public function test_avatar_menampilkan_inisial_nama(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('>RP</span>', false);
    }

    public function test_gelar_tidak_terbaca_sebagai_inisial(): void
    {
        $this->actingAs($this->pengguna(['nama' => 'Dr. Sri Handayani']))
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('>SH</span>', false);
    }

    public function test_nama_satu_kata_tetap_mendapat_inisial(): void
    {
        $this->actingAs($this->pengguna(['nama' => 'Sutarno']))
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('>S</span>', false);
    }

    public function test_logo_resmi_dipakai_pada_panel_samping(): void
    {
        $this->actingAs($this->pengguna())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('images/pangi-logo.png')
            ->assertDontSee('>PG<', false);
    }

    // ── Kelengkapan profil ──

    public function test_profil_tanpa_rekening_ditandai_perlu_dilengkapi(): void
    {
        $pengguna = $this->pengguna([
            'nama_bank' => null,
            'nomor_rekening' => null,
            'nama_rekening' => null,
            'no_hp' => null,
        ]);

        $this->actingAs($pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('Perlu dilengkapi')
            ->assertSee('bendahara tidak dapat mentransfer')
            ->assertSee('pengingat berkas tidak dapat dikirim');
    }

    public function test_profil_lengkap_ditandai_hijau(): void
    {
        $pengguna = $this->pengguna([
            'nama_bank' => 'Bank SulutGo',
            'nomor_rekening' => '1234567890',
            'nama_rekening' => 'Rahmatullah Pontoh',
            'no_hp' => '081234567890',
        ]);

        $this->actingAs($pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('Profil lengkap')
            ->assertSee('Bank SulutGo')
            ->assertDontSee('Perlu dilengkapi');
    }

    public function test_data_kepegawaian_ditampilkan_sebagai_bacaan_saja(): void
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Keperawatan']);
        $atasan = User::factory()->create(['nama' => 'Ketua Jurusan']);

        $pengguna = $this->pengguna([
            'id_unit' => $unit->id,
            'id_atasan' => $atasan->id,
            'jabatan' => 'Dosen Tetap',
        ]);

        $this->actingAs($pengguna)
            ->get(route('profil.index'))
            ->assertOk()
            ->assertSee('Data Kepegawaian')
            ->assertSee('Jurusan Keperawatan')
            ->assertSee('Ketua Jurusan')
            ->assertSee('Dosen Tetap')
            ->assertSee($pengguna->nip);
    }

    public function test_pengingat_muncul_di_panel_samping_saat_profil_belum_lengkap(): void
    {
        $belum = $this->pengguna(['nama_bank' => null, 'no_hp' => null]);
        $sudah = $this->pengguna([
            'nama' => 'Sri Handayani',
            'nama_bank' => 'Bank BRI',
            'nomor_rekening' => '9876543210',
            'nama_rekening' => 'Sri Handayani',
            'no_hp' => '081200001111',
        ]);

        $this->actingAs($belum)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Profil belum lengkap');

        $this->actingAs($sudah)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Profil belum lengkap');
    }

    public function test_semua_peran_dapat_membuka_profilnya(): void
    {
        foreach (PeranPengguna::bermodul() as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('profil.index'))
                ->assertOk();
        }
    }

    /**
     * Label peran pada dashboard dulu dipatok ke dua peran saja, sehingga
     * bendahara, PPK, pimpinan, dan super admin sama-sama tampil "Pegawai".
     */
    public function test_dashboard_menyebut_peran_pengguna_dengan_benar(): void
    {
        foreach (PeranPengguna::bermodul() as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee($peran->label());
        }
    }
}
