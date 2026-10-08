<?php

namespace Tests\Feature;

use App\Enums\ArahTiket;
use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Nominal yang diisi pelaksana selalu tampil pada rincian biaya dan dapat
 * dikoreksi tim keuangan: koreksinya bertahan saat berkas disalin ulang,
 * dan baris yang tertinggal disalin kembali ketika halamannya dibuka.
 */
class KoreksiNominalBerkasTest extends TestCase
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
        $this->usulan = Usulan::factory()->create(['id_user' => $this->pelaksana->id, 'status' => StatusUsulan::Disetujui->value]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
    }

    private function berkas(): UploadedFile
    {
        return UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf');
    }

    private function simpanTiket(int $harga): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'tiket', 'arah' => ArahTiket::Pergi->value,
            'kota_asal' => 'Manado', 'kota_tujuan' => 'Kendari', 'nomor_tiket' => '126-2148910827', 'kode_booking' => 'ABEQLR',
            'harga' => $harga, 'boarding_pass' => $this->berkas(), 'invoice' => $this->berkas(),
        ]);
    }

    private function simpanAkomodasi(): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'akomodasi', 'bill_hotel' => $this->berkas(), 'bill_hotel_no_transaksi' => '001',
            'bill_hotel_nominal' => 2_400_000, 'kwintasi' => $this->berkas(),
        ]);
    }

    private function tiket(): RincianBiaya
    {
        return RincianBiaya::where('kunci_sumber', 'tiket:pergi')->sole();
    }

    private function koreksi(RincianBiaya $baris, int $harga): TestResponse
    {
        return $this->actingAs($this->timKeuangan)->put(route('keuangan.rincian.update', [$this->usulan, $baris]), [
            'kategori' => $baris->kategori->value, 'komponen' => $baris->komponen,
            'volume' => 1, 'satuan' => $baris->satuan, 'harga_satuan' => $harga,
        ]);
    }

    public function test_koreksi_tim_keuangan_bertahan_saat_berkas_disalin_ulang(): void
    {
        $this->simpanTiket(2_510_219);
        $this->koreksi($this->tiket(), 2_510_220)->assertSessionHas('success');
        $this->actingAs($this->timKeuangan)->put(route('keuangan.rincian.validasi', [$this->usulan, $this->tiket()]));

        // Pelaksana menyimpan seksi lain — dulu ini menimpa koreksinya.
        $this->simpanAkomodasi()->assertSessionHasNoErrors();

        $this->assertSame(2_510_220.0, $this->tiket()->jumlah);
        $this->assertNotNull($this->tiket()->divalidasi_at);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertSee('Dikoreksi tim keuangan — isian pelaksana Rp 2.510.219');
    }

    public function test_nominal_yang_diubah_pelaksana_menggantikan_koreksi_dan_diperiksa_ulang(): void
    {
        $this->simpanTiket(2_510_219);
        $this->koreksi($this->tiket(), 2_510_220);
        $this->actingAs($this->timKeuangan)->put(route('keuangan.rincian.validasi', [$this->usulan, $this->tiket()]));

        $this->simpanTiket(2_600_000);

        $this->assertSame(2_600_000.0, $this->tiket()->jumlah);
        $this->assertNull($this->tiket()->divalidasi_at);
    }

    public function test_baris_berkas_yang_tertinggal_disalin_lagi_saat_halaman_dibuka(): void
    {
        $this->simpanAkomodasi();
        RincianBiaya::where('kunci_sumber', 'hotel')->delete();

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Nominal dari berkas pelaksana yang belum tercatat sudah disalin')
            ->assertSee('Uang Penginapan (No. Transaksi 001)');

        $this->assertSame(2_400_000.0, RincianBiaya::where('kunci_sumber', 'hotel')->sole()->jumlah);
    }

    public function test_baris_dari_berkas_pelaksana_tidak_dihapus(): void
    {
        $this->simpanAkomodasi();
        $hotel = RincianBiaya::where('kunci_sumber', 'hotel')->sole();

        $this->actingAs($this->timKeuangan)
            ->delete(route('keuangan.rincian.destroy', [$this->usulan, $hotel]))
            ->assertSessionHas('error');

        $this->assertModelExists($hotel);
    }

    public function test_transport_lokal_tersembunyi_dipindah_saat_halaman_dibuka(): void
    {
        $this->usulan->peserta()->create(['id_user' => $this->pelaksana->id, 'nama' => $this->pelaksana->nama, 'peran' => 'ketua']);
        RincianBiaya::factory()->create([
            'id_keuangan' => $this->usulan->keuangan->id,
            'kategori' => KategoriBiaya::TransportLokal->value,
            'komponen' => 'Transport Bandara PP',
            'volume' => 1, 'harga_satuan' => 71_500, 'jumlah' => 71_500,
        ]);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSeeInOrder(['Transport Lokal', 'Transport Bandara PP', 'Ditulis tim keuangan']);

        $this->assertSame(0, RincianBiaya::where('kategori', KategoriBiaya::TransportLokal->value)->count());
    }
}
