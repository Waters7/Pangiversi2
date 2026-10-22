<?php

namespace Tests\Feature;

use App\Enums\JenisReaksi;
use App\Models\KomentarKabar;
use App\Models\ReaksiKabar;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Komentar dan reaksi pada kartu news feed dashboard eksekutif.
 */
class InteraksiKabarTest extends TestCase
{
    use RefreshDatabase;

    private User $pimpinan;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pimpinan = User::factory()->create(['role' => User::ROLE_PIMPINAN, 'nama' => 'Dr. Hanung Prasetya']);

        $this->usulan = Usulan::factory()->create([
            'status' => 'disetujui',
            'lokasi' => 'Bitung',
            'tanggal_mulai' => today()->addDays(3)->toDateString(),
            'tanggal_selesai' => today()->addDays(4)->toDateString(),
        ]);
        $this->usulan->peserta()->create(['nama' => 'Rina Kusumawati', 'peran' => 'ketua']);
    }

    private function feed(): TestResponse
    {
        return $this->actingAs($this->pimpinan)->get(route('dashboard-eksekutif.news-feed'));
    }

    public function test_komentar_tersimpan_dan_tampil_pada_kartunya(): void
    {
        $this->actingAs($this->pimpinan)
            ->from(route('dashboard-eksekutif.news-feed'))
            ->post(route('dashboard-eksekutif.news-feed.komentar', ['perjalanan', $this->usulan->id]), [
                'isi' => 'Semangat bertugas, hati-hati di jalan.',
            ])
            ->assertRedirect(route('dashboard-eksekutif.news-feed').'#kabar-perjalanan-'.$this->usulan->id);

        $this->assertDatabaseHas(KomentarKabar::class, [
            'jenis' => 'perjalanan',
            'id_kabar' => $this->usulan->id,
            'id_user' => $this->pimpinan->id,
        ]);

        $this->feed()
            ->assertOk()
            ->assertSee('id="kabar-perjalanan-'.$this->usulan->id.'"', false)
            ->assertSeeInOrder(['Rina Kusumawati', 'Dr. Hanung Prasetya', 'Semangat bertugas, hati-hati di jalan.'])
            ->assertSee('1 komentar');
    }

    public function test_komentar_wajib_diisi(): void
    {
        $this->actingAs($this->pimpinan)
            ->post(route('dashboard-eksekutif.news-feed.komentar', ['perjalanan', $this->usulan->id]), ['isi' => ''])
            ->assertSessionHasErrors('isi');

        $this->assertDatabaseCount(KomentarKabar::class, 0);
    }

    public function test_reaksi_dipilih_diganti_dan_dicabut(): void
    {
        $bereaksi = fn (JenisReaksi $reaksi) => $this->actingAs($this->pimpinan)
            ->post(route('dashboard-eksekutif.news-feed.reaksi', ['perjalanan', $this->usulan->id]), ['reaksi' => $reaksi->value])
            ->assertRedirect();

        $bereaksi(JenisReaksi::Semangat);
        $this->assertSame(JenisReaksi::Semangat, ReaksiKabar::sole()->reaksi);
        $this->feed()->assertSee('1 reaksi')->assertSee('aria-pressed="true"', false);

        // Reaksi lain menggantinya — tetap satu reaksi per orang.
        $bereaksi(JenisReaksi::Doakan);
        $this->assertSame(JenisReaksi::Doakan, ReaksiKabar::sole()->reaksi);

        // Reaksi yang sama sekali lagi mencabutnya.
        $bereaksi(JenisReaksi::Doakan);
        $this->assertDatabaseCount(ReaksiKabar::class, 0);

        $this->actingAs($this->pimpinan)
            ->post(route('dashboard-eksekutif.news-feed.reaksi', ['perjalanan', $this->usulan->id]), ['reaksi' => 'marah'])
            ->assertSessionHasErrors('reaksi');
    }

    public function test_komentar_hanya_dihapus_penulisnya_atau_super_administrator(): void
    {
        $komentar = KomentarKabar::create([
            'jenis' => 'perjalanan',
            'id_kabar' => $this->usulan->id,
            'id_user' => $this->pimpinan->id,
            'isi' => 'Komentar pimpinan.',
        ]);
        $rekan = User::factory()->create(['role' => User::ROLE_PPK]);

        $this->actingAs($rekan)
            ->delete(route('dashboard-eksekutif.news-feed.komentar.hapus', $komentar))
            ->assertForbidden();
        $this->assertModelExists($komentar);

        $this->actingAs($this->pimpinan)
            ->delete(route('dashboard-eksekutif.news-feed.komentar.hapus', $komentar))
            ->assertRedirect();
        $this->assertModelMissing($komentar);

        $lain = KomentarKabar::create(['jenis' => 'perjalanan', 'id_kabar' => $this->usulan->id, 'id_user' => $rekan->id, 'isi' => 'Komentar PPK.']);
        $this->actingAs(User::factory()->administrator()->create())
            ->delete(route('dashboard-eksekutif.news-feed.komentar.hapus', $lain))
            ->assertRedirect();
        $this->assertModelMissing($lain);
    }

    public function test_kartu_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->pimpinan)
            ->post(route('dashboard-eksekutif.news-feed.komentar', ['perjalanan', 999999]), ['isi' => 'Halo'])
            ->assertNotFound();
        $this->actingAs($this->pimpinan)
            ->post(route('dashboard-eksekutif.news-feed.reaksi', ['lainnya', $this->usulan->id]), ['reaksi' => 'suka'])
            ->assertNotFound();
    }

    public function test_tanpa_hak_dashboard_eksekutif_tidak_dapat_berkomentar(): void
    {
        $pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($pelaksana)
            ->post(route('dashboard-eksekutif.news-feed.komentar', ['perjalanan', $this->usulan->id]), ['isi' => 'Halo'])
            ->assertForbidden();
        $this->actingAs($pelaksana)
            ->post(route('dashboard-eksekutif.news-feed.reaksi', ['perjalanan', $this->usulan->id]), ['reaksi' => 'suka'])
            ->assertForbidden();

        $this->assertDatabaseCount(KomentarKabar::class, 0);
        $this->assertDatabaseCount(ReaksiKabar::class, 0);
    }
}
