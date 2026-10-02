<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * News feed dashboard eksekutif: siapa yang akan dan sedang melakukan
 * perjalanan dinas, dan tindak lanjut yang dijadwalkan.
 */
class NewsFeedEksekutifTest extends TestCase
{
    use RefreshDatabase;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pimpinan = User::factory()->create(['role' => User::ROLE_PIMPINAN]);
    }

    private function perjalanan(string $nama, string $lokasi, int $mulaiDariHariIni, int $lama = 2, string $status = 'disetujui'): Usulan
    {
        $usulan = Usulan::factory()->create([
            'status' => $status,
            'lokasi' => $lokasi,
            'tanggal_mulai' => today()->addDays($mulaiDariHariIni)->toDateString(),
            'tanggal_selesai' => today()->addDays($mulaiDariHariIni + $lama - 1)->toDateString(),
        ]);
        $usulan->peserta()->create(['nama' => $nama, 'peran' => 'ketua']);

        return $usulan;
    }

    private function tindakLanjut(string $uraian, int $targetDariHariIni, string $status = 'rencana'): void
    {
        $usulan = $this->perjalanan('Debora Kalundang', 'Tomohon', -20, 1, 'selesai');

        $usulan->laporan()->create(['diselesaikan_at' => now()])
            ->tindakLanjut()->create([
                'uraian' => $uraian,
                'penanggung_jawab' => 'Prodi Kebidanan',
                'target_selesai' => today()->addDays($targetDariHariIni)->toDateString(),
                'status' => $status,
            ]);
    }

    public function test_kabar_memuat_pegawai_yang_akan_dan_sedang_berangkat(): void
    {
        $this->perjalanan('Rina Kusumawati', 'Bitung', 3);
        $this->perjalanan('Hanung Prasetya', 'Jakarta', 0, 3);

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard-eksekutif.news-feed'))
            ->assertOk()
            ->assertSeeInOrder(['Hari ini', 'Hanung Prasetya', 'sedang dalam perjalanan dinas', 'Hari ke-1 dari 3'])
            ->assertSee('Rina Kusumawati')
            ->assertSee('akan melakukan perjalanan dinas')
            ->assertSee('Bitung')
            ->assertSee('Berangkat 3 hari lagi');
    }

    public function test_perjalanan_yang_sudah_lewat_dan_draf_tidak_tampil(): void
    {
        $this->perjalanan('Pegawai Lampau', 'Manado', -10, 2);
        $this->perjalanan('Pegawai Draf', 'Manado', 4, 1, 'draft');

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard-eksekutif.news-feed'))
            ->assertOk()
            ->assertDontSee('Pegawai Lampau')
            ->assertDontSee('Pegawai Draf');
    }

    public function test_tindak_lanjut_terjadwal_tampil_dan_yang_lewat_tenggat_didahulukan(): void
    {
        $this->tindakLanjut('Menyusun draf revisi kurikulum', 5);
        $this->tindakLanjut('Melaporkan hasil rapat ke jurusan', -2, 'berjalan');
        $this->tindakLanjut('Tindak lanjut yang sudah tuntas', 1, 'selesai');

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard-eksekutif.news-feed'))
            ->assertOk()
            ->assertSeeInOrder([
                'Lewat tenggat', 'Melaporkan hasil rapat ke jurusan', 'Terlambat 2 hari',
                'Menyusun draf revisi kurikulum', 'Jatuh tempo 5 hari lagi',
            ])
            ->assertSee('menjadwalkan tindak lanjut')
            ->assertSee('Prodi Kebidanan')
            ->assertDontSee('Tindak lanjut yang sudah tuntas');
    }

    public function test_saringan_jenis_dan_rentang_hari(): void
    {
        $this->perjalanan('Rina Kusumawati', 'Bitung', 3);
        $this->perjalanan('Pegawai Jauh', 'Makassar', 20);
        $this->tindakLanjut('Menyusun draf revisi kurikulum', 5);

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard-eksekutif.news-feed', ['jenis' => 'tindak-lanjut']))
            ->assertOk()
            ->assertSee('Menyusun draf revisi kurikulum')
            ->assertDontSee('Rina Kusumawati');

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard-eksekutif.news-feed', ['rentang' => 7]))
            ->assertOk()
            ->assertSee('Rina Kusumawati')
            ->assertDontSee('Pegawai Jauh');
    }

    public function test_menu_dashboard_eksekutif_memuat_dua_submenu(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('dashboard-eksekutif'))
            ->assertOk()
            ->assertSee('Dashboard Utama')
            ->assertSee(route('dashboard-eksekutif.news-feed'));
    }

    public function test_hanya_peran_berhak_dashboard_eksekutif(): void
    {
        $pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($pelaksana)->get(route('dashboard-eksekutif.news-feed'))->assertForbidden();
    }
}
