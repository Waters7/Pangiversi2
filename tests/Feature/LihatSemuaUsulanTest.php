<?php

namespace Tests\Feature;

use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Notifikasi;
use App\Models\Peran;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Hak akses "Melihat seluruh usulan" (menu Usulan Perjadin → Lihat) yang
 * diberikan lewat Peran & Hak Akses benar-benar membuka daftar usulan semua
 * pegawai — dan pemegang perannya diberi tahu perubahan hak aksesnya.
 */
class LihatSemuaUsulanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $timKeuangan;

    private Usulan $usulanOrangLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $this->usulanOrangLain = Usulan::factory()->create([
            'id_user' => User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK])->id,
            'status' => StatusUsulan::Draft->value,
        ]);
    }

    private function aturHakTimKeuangan(bool $lihatSemua): TestResponse
    {
        $this->actingAs($this->admin)->get(route('administrasi.peran'));
        $peran = Peran::where('kode', PeranPengguna::TimKeuangan->value)->firstOrFail();

        $kemampuan = array_filter(
            PeranPengguna::TimKeuangan->kemampuan(),
            fn (Kemampuan $k) => $k !== Kemampuan::MelihatSemuaUsulan,
        );

        if ($lihatSemua) {
            $kemampuan[] = Kemampuan::MelihatSemuaUsulan;
        }

        return $this->actingAs($this->admin)->put(route('administrasi.peran.update', $peran), [
            'nama' => $peran->nama,
            'kemampuan' => array_map(fn (Kemampuan $k) => $k->value, array_values($kemampuan)),
        ]);
    }

    public function test_hak_melihat_seluruh_usulan_membuka_daftar_semua_pegawai(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertDontSee($this->usulanOrangLain->no_usulan);

        $this->aturHakTimKeuangan(lihatSemua: true)->assertSessionHas('success');

        $this->actingAs($this->timKeuangan)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertSee($this->usulanOrangLain->no_usulan)
            ->assertSee('Kelola seluruh usulan perjalanan dinas semua pegawai')
            // Hanya membuka daftarnya: menyunting dan menghapus tetap milik pihak terkait.
            ->assertDontSee(route('usulan.edit', $this->usulanOrangLain))
            ->assertDontSee('Hapus usulan '.$this->usulanOrangLain->no_usulan);

        $this->actingAs($this->timKeuangan)->get(route('usulan.edit', $this->usulanOrangLain))->assertForbidden();
        $this->actingAs($this->timKeuangan)->delete(route('usulan.destroy', $this->usulanOrangLain))->assertForbidden();
        $this->assertModelExists($this->usulanOrangLain);
    }

    public function test_pemegang_peran_diberi_tahu_hak_akses_yang_ditambah_dan_dicabut(): void
    {
        $this->aturHakTimKeuangan(lihatSemua: true)
            ->assertSessionHas('success', 'Hak akses peran "Tim Keuangan" tersimpan. 1 pengguna peran ini diberi tahu perubahannya.');

        $pesan = Notifikasi::where('id_user', $this->timKeuangan->id)->where('judul', 'Hak akses Anda diperbarui')->sole();
        $this->assertStringContainsString('Ditambahkan: Melihat seluruh usulan (menu Usulan Perjadin).', $pesan->pesan);

        // Menyimpan tanpa perubahan tidak mengirim apa pun.
        $this->aturHakTimKeuangan(lihatSemua: true);
        $this->assertSame(1, Notifikasi::where('judul', 'Hak akses Anda diperbarui')->count());

        $this->aturHakTimKeuangan(lihatSemua: false);
        $this->assertStringContainsString(
            'Dicabut: Melihat seluruh usulan (menu Usulan Perjadin).',
            Notifikasi::where('judul', 'Hak akses Anda diperbarui')->latest('id')->first()->pesan,
        );
    }
}
