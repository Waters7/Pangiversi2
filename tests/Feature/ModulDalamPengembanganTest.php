<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pegawai eksternal, mahasiswa, dan outsourcing akan dilayani modul tersendiri
 * yang masih dikembangkan. Sampai siap, setelah masuk mereka hanya melihat
 * halaman pemberitahuan dan tombol keluar.
 */
class ModulDalamPengembanganTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(PeranPengguna $peran): User
    {
        return User::factory()->create(['role' => $peran->value]);
    }

    public function test_hanya_pegawai_eksternal_mahasiswa_dan_outsourcing_yang_modulnya_belum_tersedia(): void
    {
        foreach (PeranPengguna::cases() as $peran) {
            $ditahan = in_array($peran, [PeranPengguna::PegawaiEksternal, PeranPengguna::Mahasiswa, PeranPengguna::Outsourcing], true);

            $this->assertSame(! $ditahan, $peran->modulTersedia(), "Peran {$peran->value}");
        }
    }

    public function test_pegawai_eksternal_diantar_ke_halaman_pemberitahuan_setelah_masuk(): void
    {
        $pengguna = $this->pengguna(PeranPengguna::PegawaiEksternal);
        $pengguna->password = 'Rahasia2026!';
        $pengguna->save();

        $this->followingRedirects()
            ->post(route('login'), ['nip' => $pengguna->nip, 'password' => 'Rahasia2026!'])
            ->assertOk()
            ->assertSee('masih dikembangkan')
            ->assertSee($pengguna->nama)
            ->assertSee('Pegawai Kemenkes Eksternal')
            ->assertSee(route('logout'));
    }

    public function test_seluruh_halaman_lain_dialihkan_ke_pemberitahuan(): void
    {
        foreach ([PeranPengguna::PegawaiEksternal, PeranPengguna::Mahasiswa, PeranPengguna::Outsourcing] as $peran) {
            $pengguna = $this->pengguna($peran);

            foreach ([route('dashboard'), route('usulan.create'), route('profil.index'), route('spd.index')] as $tujuan) {
                $this->actingAs($pengguna)
                    ->get($tujuan)
                    ->assertRedirect(route('modul.dalam-pengembangan'));
            }

            $this->actingAs($pengguna)
                ->post(route('usulan.store'), [])
                ->assertRedirect(route('modul.dalam-pengembangan'));
        }
    }

    public function test_peran_yang_ditahan_tetap_dapat_keluar(): void
    {
        $pengguna = $this->pengguna(PeranPengguna::Mahasiswa);

        $this->actingAs($pengguna)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_peran_lain_tidak_terpengaruh(): void
    {
        $dosen = $this->pengguna(PeranPengguna::DosenTendik);

        $this->actingAs($dosen)->get(route('dashboard'))->assertOk();

        // Halaman pemberitahuan bukan urusannya: diantar kembali ke dasbor.
        $this->actingAs($dosen)
            ->get(route('modul.dalam-pengembangan'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_tamu_tetap_diminta_masuk(): void
    {
        $this->get(route('modul.dalam-pengembangan'))->assertRedirect(route('login'));
    }
}
