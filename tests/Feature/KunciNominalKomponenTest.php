<?php

namespace Tests\Feature;

use App\Enums\ArahTiket;
use App\Enums\IsianBiaya;
use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Satu komponen biaya hanya dinominalkan satu pihak: nominal tim keuangan
 * mengunci isian pelaksana, dan isian pelaksana yang sudah berbukti
 * menutup komponen itu bagi tim keuangan — supaya tidak tercatat dua kali.
 */
class KunciNominalKomponenTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $timKeuangan;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
    }

    private function berkas(string $nama = 'bukti.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 100, 'application/pdf');
    }

    /**
     * @param  array<string, mixed>  $ubahan
     */
    private function tulisKeuangan(array $ubahan): TestResponse
    {
        return $this->actingAs($this->timKeuangan)->post(route('keuangan.rincian.store', $this->usulan), array_merge([
            'kategori' => KategoriBiaya::Transport->value,
            'komponen' => 'Tiket pesawat Manado–Jakarta',
            'volume' => 1,
            'satuan' => 'Tiket',
            'harga_satuan' => 2_000_000,
        ], $ubahan));
    }

    private function simpanTiket(ArahTiket $arah, int $harga = 2_450_000): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'tiket',
            'arah' => $arah->value,
            'kota_asal' => 'Manado',
            'kota_tujuan' => 'Jakarta',
            'nomor_tiket' => 'GA-602',
            'kode_booking' => 'XY7QW2',
            'harga' => $harga,
            'boarding_pass' => $this->berkas('bp.pdf'),
            'invoice' => $this->berkas('invoice.pdf'),
        ]);
    }

    private function simpanAkomodasi(int $nominal): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'akomodasi',
            'bill_hotel' => $this->berkas('bill.pdf'),
            'bill_hotel_no_transaksi' => 'TRX-88192',
            'bill_hotel_nominal' => $nominal,
            'kwintasi' => $this->berkas('kwitansi.pdf'),
        ]);
    }

    /**
     * @return Collection<int, RincianBiaya>
     */
    private function rincian()
    {
        return RincianBiaya::where('id_keuangan', $this->usulan->keuangan->id)->get();
    }

    // ── Nominal tim keuangan mengunci isian pelaksana ──

    public function test_harga_tiket_dari_tim_keuangan_mengunci_isian_pelaksana(): void
    {
        $this->tulisKeuangan(['isian_pelaksana' => IsianBiaya::TiketPergi->value])->assertSessionHas('success');

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Nominal ditetapkan tim keuangan')
            ->assertSee('Rp 2.000.000');

        // Harga yang dikirim pelaksana diabaikan dan tidak menjadi baris kedua.
        $this->simpanTiket(ArahTiket::Pergi)->assertSessionHasNoErrors();

        $this->assertNull($this->usulan->tiket()->first()->harga);
        $this->assertCount(1, $this->rincian());
        $this->assertSame(2_000_000.0, $this->rincian()->first()->jumlah);

        // Harganya juga tidak ditagih sebagai kekurangan berkas.
        $kurang = app(PenagihDokumen::class)->berkasKurang($this->usulan->fresh());
        $this->assertEmpty(array_filter($kurang, fn (string $item) => str_starts_with($item, 'Tiket Pergi')));
    }

    public function test_tiket_yang_tidak_ditetapkan_tetap_diisi_pelaksana(): void
    {
        $this->tulisKeuangan(['isian_pelaksana' => IsianBiaya::TiketPergi->value]);

        $this->simpanTiket(ArahTiket::Pulang, 2_600_000)->assertSessionHasNoErrors();

        $dariBerkas = $this->rincian()->firstWhere('sumber', RincianBiaya::SUMBER_DOKUMEN);
        $this->assertSame('tiket:pulang', $dariBerkas->kunci_sumber);
        $this->assertSame(2_600_000.0, $dariBerkas->jumlah);
    }

    public function test_tiket_pergi_pulang_dari_tim_keuangan_mengunci_kedua_tiket(): void
    {
        $this->tulisKeuangan(['isian_pelaksana' => IsianBiaya::TiketPulangPergi->value, 'volume' => 2]);

        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pulang);

        $this->assertCount(1, $this->rincian());
        $this->assertSame(0, $this->usulan->tiket()->whereNotNull('harga')->count());
    }

    public function test_penginapan_dari_tim_keuangan_mengunci_nominal_bill_hotel(): void
    {
        $this->tulisKeuangan([
            'kategori' => KategoriBiaya::Penginapan->value,
            'komponen' => 'Uang penginapan',
            'volume' => 2,
            'satuan' => 'OH',
            'harga_satuan' => 600_000,
        ])->assertSessionHas('success');

        $this->assertSame(IsianBiaya::Penginapan, $this->rincian()->first()->isian_pelaksana);

        $this->simpanAkomodasi(1_500_000)->assertSessionHasNoErrors();

        $this->assertNull($this->usulan->fresh('dokumen')->dokumen->last()->bill_hotel_nominal);
        $this->assertCount(1, $this->rincian());
        $this->assertNotContains('Nominal bill hotel', app(PenagihDokumen::class)->berkasKurang($this->usulan->fresh()));
    }

    // ── Isian pelaksana menutup komponen bagi tim keuangan ──

    public function test_tim_keuangan_tidak_dapat_menulis_tiket_yang_sudah_diisi_pelaksana(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Tiket Pergi — sudah diisi pelaksana');

        foreach ([IsianBiaya::TiketPergi, IsianBiaya::TiketPulangPergi] as $isian) {
            $this->tulisKeuangan(['isian_pelaksana' => $isian->value])
                ->assertSessionHasErrors(['isian_pelaksana' => 'Tiket Pergi (Rp 2.450.000) sudah diisi pelaksana beserta buktinya, jadi tidak ditambahkan lagi agar tidak tercatat dua kali. Periksa dan validasi baris dari pelaksana itu, atau koreksi nominalnya di sana.']);
        }

        $this->assertCount(1, $this->rincian());

        $this->tulisKeuangan(['isian_pelaksana' => IsianBiaya::TiketPulang->value])->assertSessionHas('success');
        $this->assertCount(2, $this->rincian());
    }

    public function test_tim_keuangan_tidak_dapat_menulis_penginapan_yang_sudah_diisi_pelaksana(): void
    {
        $this->simpanAkomodasi(1_200_000);

        $this->tulisKeuangan([
            'kategori' => KategoriBiaya::Penginapan->value,
            'komponen' => 'Uang penginapan',
            'satuan' => 'OH',
            'harga_satuan' => 600_000,
        ])->assertSessionHasErrors('isian_pelaksana');

        $this->assertCount(1, $this->rincian());
    }

    public function test_menyunting_baris_menjadi_tiket_yang_sudah_diisi_pelaksana_ditolak(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->tulisKeuangan([
            'kategori' => KategoriBiaya::Lainnya->value,
            'komponen' => 'Airport tax',
            'satuan' => 'Paket',
            'harga_satuan' => 150_000,
        ]);
        $baris = $this->rincian()->firstWhere('sumber', RincianBiaya::SUMBER_KEUANGAN);

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.update', [$this->usulan, $baris]), [
                'kategori' => KategoriBiaya::Transport->value,
                'komponen' => 'Tiket pesawat',
                'volume' => 1,
                'satuan' => 'Tiket',
                'harga_satuan' => 2_000_000,
                'isian_pelaksana' => IsianBiaya::TiketPergi->value,
            ])
            ->assertSessionHas('error');

        $this->assertSame(KategoriBiaya::Lainnya, $baris->fresh()->kategori);
    }

    // ── Aturan baris tim keuangan ──

    public function test_baris_transport_wajib_menyebut_tiket_yang_diwakili(): void
    {
        $this->tulisKeuangan([])->assertSessionHasErrors('isian_pelaksana');

        $this->tulisKeuangan(['isian_pelaksana' => IsianBiaya::BukanTiket->value])->assertSessionHas('success');

        // Transport lain tidak mengunci tiket pelaksana.
        $this->simpanTiket(ArahTiket::Pergi);
        $this->assertCount(2, $this->rincian());
    }

    public function test_komponen_ganda_dari_data_lama_ditandai(): void
    {
        $this->simpanAkomodasi(1_200_000);

        // Baris lama tulisan tim keuangan, dari sebelum aturan ini berlaku.
        RincianBiaya::factory()->create([
            'id_keuangan' => $this->usulan->keuangan->id,
            'kategori' => KategoriBiaya::Penginapan->value,
            'komponen' => 'Uang penginapan',
            'jumlah' => 1_000_000,
            'isian_pelaksana' => null,
        ]);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Komponen mungkin tercatat dua kali');
    }
}
