<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nominal berkas yang dulu gagal masuk rincian biaya — biaya penyelenggaraan
 * yang ditolak kolom kategori ber-ENUM di MySQL — disusulkan lewat
 * pangi:selaraskan-biaya.
 */
class SelaraskanBiayaBerkasTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->usulan = Usulan::factory()->create(['id_user' => $pelaksana->id, 'status' => StatusUsulan::Disetujui->value]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->actingAs($pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'penyelenggaraan',
            'penyelenggaraan_ada' => '1',
            'penyelenggaraan_nominal' => 2_400_000,
            'penyelenggaraan_invoice' => '001',
            'penyelenggaraan_bukti' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    private function barisPenyelenggaraan(): ?RincianBiaya
    {
        return RincianBiaya::where('id_keuangan', $this->usulan->keuangan->id)
            ->where('kunci_sumber', 'penyelenggaraan')
            ->first();
    }

    public function test_nominal_berkas_yang_tertinggal_disusulkan_ke_rincian(): void
    {
        // Seperti di peladen MySQL sebelum perbaikan: barisnya tidak pernah tersimpan.
        $this->barisPenyelenggaraan()->delete();

        $this->artisan('pangi:selaraskan-biaya')
            ->expectsOutputToContain($this->usulan->no_usulan.': 1 ditambah')
            ->assertSuccessful();

        $baris = $this->barisPenyelenggaraan();
        $this->assertSame(KategoriBiaya::Penyelenggaraan, $baris->kategori);
        $this->assertSame(2_400_000.0, $baris->jumlah);
        $this->assertSame(2_400_000.0, (float) $this->usulan->keuangan->fresh()->total);
    }

    public function test_tanpa_nominal_tertinggal_tidak_ada_yang_diubah(): void
    {
        $this->artisan('pangi:selaraskan-biaya')
            ->expectsOutput('Tidak ada nominal berkas yang tertinggal dari rincian biaya.')
            ->assertSuccessful();

        $this->assertSame(1, RincianBiaya::count());
    }
}
