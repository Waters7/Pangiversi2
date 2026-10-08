<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Keuangan → Input Rincian Biaya menautkan surat tugas pelaksana, baik
 * dari daftar usulannya maupun dari halaman rincian biayanya.
 */
class SuratTugasKeuanganTest extends TestCase
{
    use RefreshDatabase;

    private User $timKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
    }

    private function usulan(): Usulan
    {
        $usulan = Usulan::factory()->create(['status' => 'disetujui', 'no_tugas' => 'KP.01.02/F.XXX/0815/2026']);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        return $usulan;
    }

    public function test_surat_tugas_pelaksana_dapat_dibuka_dari_daftar_dan_rincian(): void
    {
        $usulan = $this->usulan();
        $berkas = Dokumen::factory()->create(['id_usulan' => $usulan->id])->surat_tugas;

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee(route('berkas.lihat', $berkas), false);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $usulan))
            ->assertOk()
            ->assertSeeInOrder(['Surat Tugas', 'KP.01.02/F.XXX/0815/2026', 'Lihat surat tugas'])
            ->assertSee(route('berkas.lihat', $berkas), false);
    }

    public function test_usulan_lama_memakai_surat_tugas_yang_melekat_pada_spd(): void
    {
        $usulan = $this->usulan();
        $spd = $this->terbitkanSpd($usulan->user);
        $spd->update(['surat_tugas' => 'dokumen/surat-tugas/dari-spd.pdf']);
        $usulan->update(['id_spd' => $spd->id]);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $usulan))
            ->assertSee(route('berkas.lihat', 'dokumen/surat-tugas/dari-spd.pdf'), false);
    }

    public function test_surat_tugas_yang_belum_diunggah_disebutkan(): void
    {
        $usulan = $this->usulan();

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $usulan))
            ->assertSee('Berkasnya belum diunggah')
            ->assertDontSee('Lihat surat tugas');
    }
}
