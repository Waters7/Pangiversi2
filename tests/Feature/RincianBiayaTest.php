<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Kegiatan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use App\Services\Terbilang;
use Database\Seeders\KegiatanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RincianBiayaTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private User $timKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $this->usulan->keuangan()->create(['total' => 0, 'uang_muka' => 0, 'sisa' => 0]);

        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function dataRincian(array $ubahan = []): array
    {
        return array_merge([
            'kategori' => KategoriBiaya::UangHarian->value,
            'komponen' => 'Uang Harian',
            'volume' => 2,
            'satuan' => 'Hari',
            'harga_satuan' => 370_000,
        ], $ubahan);
    }

    // ── Kategori ──

    public function test_rincian_disimpan_dengan_kategori_resmi(): void
    {
        $this->actingAs($this->timKeuangan)
            ->post(route('keuangan.rincian.store', $this->usulan), $this->dataRincian())
            ->assertSessionHas('success');

        $rincian = RincianBiaya::first();

        $this->assertSame(KategoriBiaya::UangHarian, $rincian->kategori);
        $this->assertSame(740_000.0, $rincian->jumlah);
    }

    public function test_kategori_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->timKeuangan)
            ->post(route('keuangan.rincian.store', $this->usulan), $this->dataRincian([
                'kategori' => 'biaya_siluman',
            ]))
            ->assertSessionHasErrors('kategori');
    }

    public function test_kategori_terurut_sesuai_penomoran_pmk(): void
    {
        $urutan = array_map(
            fn (KategoriBiaya $k) => $k->value,
            KategoriBiaya::urutanCetak(),
        );

        $this->assertSame([
            'transport',
            'uang_harian',
            'transport_lokal',
            'penginapan',
            'penyelenggaraan',
            'lainnya',
        ], $urutan);
    }

    public function test_uang_harian_diuraikan_sebagai_volume_kali_tarif(): void
    {
        $rincian = RincianBiaya::factory()->create([
            'id_keuangan' => $this->usulan->keuangan->id,
            'kategori' => KategoriBiaya::UangHarian->value,
            'volume' => 2,
            'satuan' => 'Hari',
            'harga_satuan' => 370_000,
        ]);

        $this->assertSame('2 Hari x Rp 370.000', $rincian->uraian);
    }

    // ── Cetak rincian ──

    public function test_rincian_dapat_dicetak_dalam_format_pmk(): void
    {
        $this->actingAs($this->timKeuangan)
            ->post(route('keuangan.rincian.store', $this->usulan), $this->dataRincian());

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.cetak-rincian', $this->usulan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_peran_tanpa_akses_keuangan_tidak_dapat_mencetak(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
            ->get(route('keuangan.cetak-rincian', $this->usulan))
            ->assertForbidden();
    }

    // ── Terbilang ──

    public function test_terbilang_mengeja_bilangan_dalam_bahasa_indonesia(): void
    {
        $terbilang = new Terbilang;

        $this->assertSame('nol rupiah', $terbilang->konversi(0));
        $this->assertSame('seribu rupiah', $terbilang->konversi(1_000));
        $this->assertSame('dua ratus lima puluh ribu rupiah', $terbilang->konversi(250_000));
        $this->assertSame(
            'satu juta enam ratus dua puluh ribu rupiah',
            $terbilang->konversi(1_620_000)
        );
    }

    // ── Nota transportasi ──

    /**
     * Nota transportasi kini berupa empat ruas bernominal, bukan satu
     * kolom unggahan — jadi kelengkapannya diperiksa lewat penagih,
     * bukan lewat daftar kolom berkas.
     */
    public function test_nota_transportasi_ikut_menentukan_kelengkapan(): void
    {
        $usulan = $this->lengkapiPertanggungjawaban($this->usulan);
        $usulan->notaTransport()->update(['bukti' => null]);

        $kurang = app(PenagihDokumen::class)->berkasKurang($usulan->fresh('notaTransport'));

        $this->assertContains('Nota transportasi lokal untuk ruas 1', $kurang);
    }

    /**
     * Transport lokal boleh tidak ada sama sekali; yang menahan hanyalah
     * nominal yang belum berbukti.
     */
    public function test_usulan_belum_selesai_selama_nota_bernominal_tanpa_bukti(): void
    {
        $this->usulan->keuangan->update(['status' => 'lunas']);

        $usulan = $this->lengkapiPertanggungjawaban($this->usulan);
        $usulan->notaTransport()->update(['bukti' => null]);

        $this->assertFalse($this->usulan->fresh()->checkCompletion());
        $this->assertSame(StatusUsulan::Disetujui->value, $this->usulan->fresh()->status);

        $usulan->notaTransport()->delete();

        $this->assertTrue($this->usulan->fresh()->checkCompletion());
    }

    public function test_usulan_selesai_setelah_nota_transportasi_dilengkapi(): void
    {
        $this->usulan->keuangan->update(['status' => 'lunas']);

        $this->lengkapiPertanggungjawaban($this->usulan);

        $this->assertTrue($this->usulan->fresh()->checkCompletion());
        $this->assertSame(StatusUsulan::Selesai->value, $this->usulan->fresh()->status);
    }

    // ── Jenis kegiatan ──

    public function test_jenis_kegiatan_resmi_tersedia_setelah_seeding(): void
    {
        $this->seed(KegiatanSeeder::class);

        $resmi = [
            'Mengikuti rapat, seminar, lokakarya, atau studi banding',
            'Melaksanakan tugas dan fungsi jabatan atau kunjungan kerja',
            'Mengikuti pendidikan dan pelatihan (diklat) atau kursus singkat',
            'Menempuh ujian dinas atau menghadap majelis penguji kesehatan',
            'Mengikuti pameran, promosi, atau sidang internasional',
        ];

        foreach ($resmi as $nama) {
            $this->assertDatabaseHas('kegiatan', ['nama' => $nama]);
        }

        // Seeder tidak menduplikasi bila dijalankan ulang.
        $this->seed(KegiatanSeeder::class);

        $this->assertSame(5, Kegiatan::whereIn('nama', $resmi)->count());
    }
}
