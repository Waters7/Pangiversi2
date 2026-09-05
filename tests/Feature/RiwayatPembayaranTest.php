<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\RiwayatPembayaran;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Jurnal pembayaran bendahara.
 *
 * Tabel keuangan menyatakan keadaan terakhir sebuah perjalanan; jurnal ini
 * menyatakan riwayatnya — siapa membayar, kapan, berapa, dan dengan bukti
 * apa. Halaman riwayat menjawab pertanyaan yang tidak terjawab daftar
 * tahap: apa saja yang sudah saya bayarkan bulan ini.
 */
class RiwayatPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);

        $this->usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-04-06',
            'tanggal_selesai' => '2026-04-08',
        ]);
    }

    private function catat(string $jenis, float $nominal, string $tanggal): RiwayatPembayaran
    {
        $keuangan = $this->usulan->keuangan
            ?? Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        return RiwayatPembayaran::catat(
            $keuangan->fresh(),
            $jenis,
            $nominal,
            $tanggal,
            $this->bendahara,
            'keuangan/bukti.pdf',
        );
    }

    private function buka(array $parameter = []): TestResponse
    {
        return $this->actingAs($this->bendahara)
            ->get(route('pembayaran.riwayat', $parameter))
            ->assertOk();
    }

    private function jumlahTampil(TestResponse $halaman): int
    {
        return $halaman->viewData('riwayat')->flatten(1)->count();
    }

    // ── Pencatatan ──

    public function test_pembayaran_uang_muka_masuk_jurnal(): void
    {
        $keuangan = Keuangan::factory()->belumBayar()->create([
            'id_usulan' => $this->usulan->id,
            'total' => 5_000_000,
            'uang_muka' => 4_000_000,
            'sisa' => 1_000_000,
        ]);

        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-uang-muka', $this->usulan), [
                'tanggal_transfer' => '2026-04-04',
                'bukti_transfer' => UploadedFile::fake()->create('um.pdf', 40, 'application/pdf'),
            ]);

        $baris = RiwayatPembayaran::firstWhere('id_keuangan', $keuangan->id);

        $this->assertNotNull($baris, 'Pembayaran uang muka tidak tercatat pada jurnal.');
        $this->assertSame(RiwayatPembayaran::JENIS_UANG_MUKA, $baris->jenis);
        $this->assertSame($this->bendahara->id, $baris->id_pencatat);
        $this->assertNotNull($baris->bukti, 'Jurnal tidak menyimpan bukti transfernya.');
    }

    // ── Tampilan riwayat ──

    public function test_riwayat_dikelompokkan_per_bulan(): void
    {
        $this->catat(RiwayatPembayaran::JENIS_UANG_MUKA, 4_000_000, '2026-04-04');
        $this->catat(RiwayatPembayaran::JENIS_PELUNASAN, 1_000_000, '2026-07-11');

        // Terbaru di atas.
        $this->buka()->assertSeeInOrder(['Juli 2026', 'April 2026']);
    }

    public function test_riwayat_disaring_menurut_jenis(): void
    {
        $this->catat(RiwayatPembayaran::JENIS_UANG_MUKA, 4_000_000, '2026-04-04');
        $this->catat(RiwayatPembayaran::JENIS_PELUNASAN, 1_000_000, '2026-04-20');

        $this->assertSame(1, $this->jumlahTampil(
            $this->buka(['jenis' => RiwayatPembayaran::JENIS_PELUNASAN])
        ));
    }

    public function test_riwayat_disaring_menurut_bulan_dan_tahun(): void
    {
        $this->catat(RiwayatPembayaran::JENIS_UANG_MUKA, 4_000_000, '2026-04-04');
        $this->catat(RiwayatPembayaran::JENIS_PELUNASAN, 1_000_000, '2025-11-02');

        $this->assertSame(1, $this->jumlahTampil($this->buka(['bulan' => 4])));
        $this->assertSame(1, $this->jumlahTampil($this->buka(['tahun' => 2025])));
        $this->assertSame(0, $this->jumlahTampil($this->buka(['tahun' => 2025, 'bulan' => 4])));
    }

    public function test_nilai_bersih_dikurangi_pembatalan(): void
    {
        $this->catat(RiwayatPembayaran::JENIS_UANG_MUKA, 4_000_000, '2026-04-04');
        $this->catat(RiwayatPembayaran::JENIS_BATAL_UANG_MUKA, 4_000_000, '2026-04-05');

        // Yang dibayarkan lalu ditarik kembali tidak boleh terhitung dua kali.
        $this->buka()->assertViewHas('totalTerbayar', 0.0);
    }

    public function test_riwayat_menyebut_pencatatnya(): void
    {
        $this->catat(RiwayatPembayaran::JENIS_UANG_MUKA, 4_000_000, '2026-04-04');

        $this->buka()->assertSee($this->bendahara->nama);
    }

    public function test_menu_pembayaran_menawarkan_submenu_riwayat(): void
    {
        $this->actingAs($this->bendahara)
            ->get(route('pembayaran'))
            ->assertOk()
            ->assertSee('Riwayat Pembayaran')
            ->assertSee(route('pembayaran.riwayat'), false);
    }

    public function test_riwayat_tertutup_bagi_peran_tanpa_hak_pembayaran(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::DosenTendik->value]))
            ->get(route('pembayaran.riwayat'))
            ->assertForbidden();
    }
}
