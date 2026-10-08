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
        $this->barisPenyelenggaraan()->forceDelete();

        $this->artisan('pangi:selaraskan-biaya')
            ->expectsOutputToContain($this->usulan->no_usulan.': 1 ditambah')
            ->assertSuccessful();

        $baris = $this->barisPenyelenggaraan();
        $this->assertSame(KategoriBiaya::Penyelenggaraan, $baris->kategori);
        $this->assertSame(2_400_000.0, $baris->jumlah);

        // Tersusul sebagai nominal pelaksana yang menunggu validasi.
        $this->assertNull($baris->divalidasi_at);
    }

    /**
     * Total yang tersimpan menurut aturan lama dihitung ulang — hanya baris
     * sah — kecuali yang sudah lunas: angkanya sudah dibayarkan apa adanya.
     */
    public function test_total_yang_belum_lunas_dihitung_ulang(): void
    {
        $this->usulan->keuangan->update(['total' => 2_400_000, 'uang_muka' => 2_400_000, 'sisa' => 0]);

        $lunas = Keuangan::factory()->lunas()->create([
            'id_usulan' => Usulan::factory()->create()->id,
            'total' => 5_000_000,
        ]);

        $this->artisan('pangi:selaraskan-biaya')
            ->expectsOutputToContain('rincian biaya yang belum lunas dihitung ulang')
            ->assertSuccessful();

        $this->assertSame(0.0, $this->usulan->keuangan->fresh()->total);
        $this->assertSame(5_000_000.0, $lunas->fresh()->total);
    }

    public function test_tanpa_nominal_tertinggal_tidak_ada_yang_diubah(): void
    {
        $this->artisan('pangi:selaraskan-biaya')
            ->expectsOutput('Tidak ada nominal berkas yang tertinggal dari rincian biaya maupun daftar riil.')
            ->assertSuccessful();

        $this->assertSame(1, RincianBiaya::count());
    }
}
