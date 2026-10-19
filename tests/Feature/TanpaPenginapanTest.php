<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Luar kota dalam satu provinsi kerap tanpa menginap: pelaksananya ditanya
 * dulu ada atau tidaknya biaya penginapan sebelum ditagih bill hotel dan
 * kuitansinya.
 */
class TanpaPenginapanTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    private function usulan(JenisPerjadin $jalur): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'jenis_perjadin' => $jalur->value,
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        return $usulan;
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function simpanAkomodasi(Usulan $usulan, array $isian): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $usulan), ['section' => 'akomodasi'] + $isian);
    }

    /** @return list<string> */
    private function berkasKurang(Usulan $usulan): array
    {
        return app(PenagihDokumen::class)->berkasKurang($usulan->fresh());
    }

    public function test_hanya_luar_kota_dalam_provinsi_yang_ditanya(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan(JenisPerjadin::LuarKotaDalamProvinsi)))
            ->assertOk()
            ->assertSee('Apakah ada biaya penginapan?');

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan(JenisPerjadin::LuarKota)))
            ->assertOk()
            ->assertDontSee('Apakah ada biaya penginapan?');
    }

    public function test_tanpa_penginapan_bill_hotel_dan_kuitansi_tidak_ditagih(): void
    {
        $usulan = $this->usulan(JenisPerjadin::LuarKotaDalamProvinsi);
        $this->assertContains('Bill hotel', $this->berkasKurang($usulan));

        $this->simpanAkomodasi($usulan, ['penginapan_ada' => '0'])->assertSessionHasNoErrors();

        $this->assertTrue($usulan->fresh('dokumen')->dokumen->last()->tanpaPenginapan());
        $this->assertNotContains('Bill hotel', $this->berkasKurang($usulan));
        $this->assertNotContains('Kuitansi penyelenggara / hotel', $this->berkasKurang($usulan));

        $baris = collect(app(PenagihDokumen::class)->checklist($usulan->fresh()))->keyBy('label');
        $this->assertTrue($baris['Bill Hotel']['terpenuhi']);
        $this->assertTrue($baris['Kuitansi penyelenggara / hotel']['terpenuhi']);
    }

    public function test_beralih_ke_tanpa_penginapan_mengosongkan_bill_hotel_dan_uang_penginapannya(): void
    {
        $usulan = $this->usulan(JenisPerjadin::LuarKotaDalamProvinsi);

        $this->simpanAkomodasi($usulan, [
            'penginapan_ada' => '1',
            'bill_hotel' => UploadedFile::fake()->create('bill.pdf', 100, 'application/pdf'),
            'bill_hotel_no_transaksi' => 'TRX-88192',
            'bill_hotel_nominal' => 1_200_000,
            'kwintasi' => UploadedFile::fake()->create('kwitansi.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $berkasHotel = $usulan->fresh('dokumen')->dokumen->last()->bill_hotel;
        $this->assertTrue($usulan->keuangan->rincianBiaya()->where('kategori', KategoriBiaya::Penginapan->value)->exists());

        $this->simpanAkomodasi($usulan, ['penginapan_ada' => '0'])->assertSessionHasNoErrors();

        $dokumen = $usulan->fresh('dokumen')->dokumen->last();
        $this->assertNull($dokumen->bill_hotel);
        $this->assertNull($dokumen->bill_hotel_no_transaksi);
        $this->assertNull($dokumen->bill_hotel_nominal);
        Storage::disk('public')->assertMissing($berkasHotel);
        $this->assertFalse($usulan->keuangan->rincianBiaya()->where('kategori', KategoriBiaya::Penginapan->value)->exists());
    }

    public function test_tanpa_jawaban_penginapan_dianggap_ada(): void
    {
        $usulan = $this->usulan(JenisPerjadin::LuarKotaDalamProvinsi);

        $this->simpanAkomodasi($usulan, [])->assertSessionHasErrors('bill_hotel');
        $this->assertContains('Bill hotel', $this->berkasKurang($usulan));
    }

    public function test_luar_kota_lintas_pulau_tetap_wajib_bill_hotel(): void
    {
        $usulan = $this->usulan(JenisPerjadin::LuarKota);

        $this->simpanAkomodasi($usulan, ['penginapan_ada' => '0'])->assertSessionHasErrors('bill_hotel');
        $this->assertContains('Bill hotel', $this->berkasKurang($usulan));
    }
}
