<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel akun pindah dari sidebar ke sudut kanan atas, menyatu dengan
 * lonceng notifikasi.
 */
class PanelAkunTopbarTest extends TestCase
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

    public function test_panel_akun_berada_di_topbar(): void
    {
        $isi = $this->actingAs($this->pengguna())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $topbar = $this->potongTopbar($isi);

        $this->assertStringContainsString('Rahmatullah Pontoh', $topbar);
        $this->assertStringContainsString('Profil Saya', $topbar);
        $this->assertStringContainsString('Keluar', $topbar);
    }

    public function test_sidebar_tidak_lagi_memuat_panel_akun(): void
    {
        $isi = $this->actingAs($this->pengguna())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $sidebar = $this->potongSidebar($isi);

        $this->assertStringNotContainsString('Profil Saya', $sidebar);
        $this->assertStringNotContainsString('Keluar', $sidebar);
        // Menu navigasinya tetap utuh.
        $this->assertStringContainsString('Panduan Penggunaan', $sidebar);
    }

    public function test_panel_menampilkan_nip_dan_peran(): void
    {
        $pengguna = $this->pengguna(['role' => PeranPengguna::Ppk->value]);

        $this->actingAs($pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($pengguna->nip)
            ->assertSee('PPK');
    }

    public function test_pengingat_profil_muncul_saat_data_belum_lengkap(): void
    {
        $this->actingAs($this->pengguna(['nama_bank' => null, 'no_hp' => null]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Profil belum lengkap')
            ->assertSee('rekening atau nomor WhatsApp masih kosong');
    }

    public function test_profil_lengkap_tanpa_pengingat(): void
    {
        $this->actingAs($this->pengguna([
            'nama_bank' => 'Bank SulutGo',
            'nomor_rekening' => '1234567890',
            'nama_rekening' => 'Rahmatullah Pontoh',
            'no_hp' => '081234567890',
        ]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Profil belum lengkap');
    }

    public function test_tautan_keluar_memakai_metode_post(): void
    {
        $isi = $this->actingAs($this->pengguna())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form[^>]*method="POST"[^>]*action="[^"]*\/logout"/',
            $this->potongTopbar($isi)
        );
    }

    public function test_semua_peran_melihat_panel_akunnya(): void
    {
        foreach (PeranPengguna::bermodul() as $peran) {
            $pengguna = User::factory()->create(['role' => $peran->value]);

            $this->actingAs($pengguna)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee($pengguna->nama);
        }
    }

    private function potongTopbar(string $isi): string
    {
        return $this->potong($isi, '<header', '</header>');
    }

    private function potongSidebar(string $isi): string
    {
        return $this->potong($isi, '<aside', '</aside>');
    }

    private function potong(string $isi, string $awal, string $akhir): string
    {
        $mulai = strpos($isi, $awal);
        $selesai = strpos($isi, $akhir, $mulai ?: 0);

        $this->assertNotFalse($mulai, "Penanda {$awal} tidak ditemukan.");
        $this->assertNotFalse($selesai, "Penanda {$akhir} tidak ditemukan.");

        return substr($isi, $mulai, $selesai - $mulai);
    }
}
