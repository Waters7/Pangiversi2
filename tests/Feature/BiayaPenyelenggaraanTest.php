<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use App\Services\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Biaya penyelenggaraan — kontribusi/registrasi kegiatan yang dibayar
 * pelaksana — ditanya dulu ada atau tidak. Bila ada, nominal dan bukti
 * bayarnya wajib dan nominalnya mengalir ke rincian biaya di bawah uang
 * penginapan; bila tidak, tidak satu pun kolomnya ditagih.
 */
class BiayaPenyelenggaraanTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.03.01/F.XXXVIII/81/2026',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     */
    private function simpan(array $ubahan = []): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), array_merge([
            'section' => 'penyelenggaraan',
            'penyelenggaraan_ada' => '1',
            'penyelenggaraan_nominal' => 1_500_000,
            'penyelenggaraan_invoice' => 'INV/2026/0123',
            'penyelenggaraan_bukti' => UploadedFile::fake()->create('bukti-bayar.pdf', 60, 'application/pdf'),
        ], $ubahan));
    }

    private function dokumen(): ?Dokumen
    {
        return Dokumen::where('id_usulan', $this->usulan->id)->latest('id')->first();
    }

    private function rincianPenyelenggaraan(): ?RincianBiaya
    {
        return $this->usulan->fresh('keuangan')->keuangan->rincianBiaya
            ->first(fn (RincianBiaya $b) => $b->kategori === KategoriBiaya::Penyelenggaraan);
    }

    // ── Formulir ──

    public function test_formulir_menanyakan_ada_tidaknya_biaya_penyelenggaraan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('5. Biaya Penyelenggaraan')
            ->assertSee('Apakah ada biaya penyelenggaraan?')
            ->assertSee('name="penyelenggaraan_ada"', escape: false)
            ->assertSee('name="penyelenggaraan_nominal"', escape: false)
            ->assertSee('name="penyelenggaraan_invoice"', escape: false)
            ->assertSee('name="penyelenggaraan_bukti"', escape: false)
            ->assertSee('6. Laporan Perjalanan Dinas');
    }

    // ── Menyimpan ──

    public function test_bila_ada_nominal_bukti_dan_invoice_tersimpan_dan_mengalir_ke_rincian(): void
    {
        $this->simpan()->assertSessionHasNoErrors();

        $dokumen = $this->dokumen();

        $this->assertTrue($dokumen->adaPenyelenggaraan());
        $this->assertSame(1_500_000.0, $dokumen->penyelenggaraan_nominal);
        $this->assertSame('INV/2026/0123', $dokumen->penyelenggaraan_invoice);
        $this->assertNotNull($dokumen->penyelenggaraan_bukti);

        $baris = $this->rincianPenyelenggaraan();

        $this->assertNotNull($baris, 'Biaya penyelenggaraan tidak masuk rincian biaya.');
        $this->assertSame('Biaya Penyelenggaraan (No. Invoice INV/2026/0123)', $baris->komponen);
        $this->assertSame(1_500_000.0, (float) $baris->jumlah);
        $this->assertSame(RincianBiaya::SUMBER_DOKUMEN, $baris->sumber);
    }

    public function test_nomor_invoice_boleh_kosong(): void
    {
        $this->simpan(['penyelenggaraan_invoice' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->dokumen()->penyelenggaraan_invoice);
        $this->assertSame('Biaya Penyelenggaraan', $this->rincianPenyelenggaraan()->komponen);
    }

    public function test_bila_ada_nominal_dan_bukti_wajib(): void
    {
        $this->simpan(['penyelenggaraan_nominal' => '', 'penyelenggaraan_bukti' => null])
            ->assertSessionHasErrors(['penyelenggaraan_nominal', 'penyelenggaraan_bukti']);

        $this->assertNull($this->dokumen());
    }

    public function test_bukti_yang_sudah_ada_tidak_ditagih_lagi(): void
    {
        $this->simpan();

        $this->simpan(['penyelenggaraan_nominal' => 1_750_000, 'penyelenggaraan_bukti' => null])
            ->assertSessionHasNoErrors();

        $this->assertSame(1_750_000.0, $this->dokumen()->penyelenggaraan_nominal);
        $this->assertSame(1_750_000.0, (float) $this->rincianPenyelenggaraan()->jumlah);
    }

    /**
     * Menjawab "tidak" menghapus isian lama seluruhnya, termasuk baris
     * rincian biayanya — tidak ada nominal yang tertinggal diam-diam.
     */
    public function test_bila_tidak_seluruh_isian_dikosongkan(): void
    {
        $this->simpan();
        $bukti = $this->dokumen()->penyelenggaraan_bukti;

        $this->simpan(['penyelenggaraan_ada' => '0', 'penyelenggaraan_nominal' => '', 'penyelenggaraan_bukti' => null])
            ->assertSessionHasNoErrors();

        $dokumen = $this->dokumen();

        $this->assertFalse($dokumen->adaPenyelenggaraan());
        $this->assertNull($dokumen->penyelenggaraan_nominal);
        $this->assertNull($dokumen->penyelenggaraan_bukti);
        Storage::disk('public')->assertMissing($bukti);
        $this->assertNull($this->rincianPenyelenggaraan());
    }

    // ── Kelengkapan & checklist ──

    public function test_checklist_hanya_memuat_biaya_penyelenggaraan_bila_ada(): void
    {
        $penagih = app(PenagihDokumen::class);

        $label = collect($penagih->checklist($this->usulan->fresh()))->pluck('label');
        $this->assertNotContains('Bukti Biaya Penyelenggaraan', $label);

        $this->simpan();

        $baris = collect($penagih->checklist($this->usulan->fresh()))
            ->firstWhere('label', 'Bukti Biaya Penyelenggaraan');

        $this->assertNotNull($baris);
        $this->assertTrue($baris['terpenuhi']);
        $this->assertSame('Bukti bayar', $baris['berkas'][0]['label']);
        $this->assertStringContainsString('INV/2026/0123', $baris['catatan']);
    }

    public function test_yang_menyatakan_ada_tanpa_bukti_ditagih(): void
    {
        Dokumen::create([
            'id_usulan' => $this->usulan->id,
            'penyelenggaraan_ada' => true,
            'penyelenggaraan_nominal' => 500_000,
        ]);

        $kurang = app(PenagihDokumen::class)->berkasKurang($this->usulan->fresh());

        $this->assertContains('Bukti bayar biaya penyelenggaraan', $kurang);
    }

    public function test_yang_menyatakan_tidak_ada_tidak_ditagih(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        Dokumen::where('id_usulan', $this->usulan->id)->update(['penyelenggaraan_ada' => false]);

        $this->assertTrue(app(PenagihDokumen::class)->lengkap($this->usulan->fresh()));
    }

    // ── Rincian, cetakan, dan nominatif ──

    public function test_kategori_berada_di_bawah_uang_penginapan(): void
    {
        $urutan = array_map(fn (KategoriBiaya $k) => $k->value, KategoriBiaya::urutanCetak());

        $this->assertSame(
            ['transport', 'uang_harian', 'transport_lokal', 'penginapan', 'penyelenggaraan', 'lainnya'],
            $urutan,
        );
        $this->assertArrayHasKey('penyelenggaraan', KategoriBiaya::options());
    }

    public function test_cetakan_rincian_menyusun_biaya_penyelenggaraan_di_bawah_akomodasi(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        $this->simpan();
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);

        $this->actingAs($timKeuangan)
            ->get(route('keuangan.cetak-rincian', $this->usulan->no_usulan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $html = view('keuangan.cetak-rincian', $this->dataCetakRincian())->render();

        $this->assertStringContainsString('Biaya Penyelenggaraan (No. Invoice INV/2026/0123)', $html);
        $this->assertLessThan(
            strpos($html, 'Biaya Penyelenggaraan</td>'),
            strpos($html, 'Biaya Akomodasi</td>'),
            'Kelompok biaya penyelenggaraan harus di bawah biaya akomodasi.',
        );
    }

    public function test_nominatif_menghitung_biaya_penyelenggaraan_pada_jumlah_pembayaran(): void
    {
        $this->usulan->peserta()->create(['id_user' => $this->pelaksana->id, 'nama' => $this->pelaksana->nama, 'peran' => 'ketua']);
        $this->lengkapiPertanggungjawaban($this->usulan);
        $this->simpan();
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
        $this->tandatanganiBerkas($this->usulan->fresh());

        $baris = app(PenyusunNominatif::class)->baris($this->usulan->no_tugas)->first();
        $total = (float) $this->usulan->fresh('keuangan')->keuangan->rincianBiaya
            ->reject(fn (RincianBiaya $b) => $b->kategori === KategoriBiaya::TransportLokal)
            ->sum('jumlah');

        $this->assertSame($total + $baris['transport'], $baris['jumlah']);
        $this->assertGreaterThanOrEqual(1_500_000.0, $baris['jumlah']);
    }

    /**
     * Data yang sama dengan yang dipakai pengontrol cetak rincian, supaya
     * templatnya dapat dirender sebagai HTML untuk diperiksa.
     *
     * @return array<string, mixed>
     */
    private function dataCetakRincian(): array
    {
        $usulan = $this->usulan->fresh(['user.unit', 'kegiatan', 'keuangan.rincianBiaya', 'peserta', 'daftarRiil']);
        $rincian = $usulan->keuangan->rincianBiaya
            ->reject(fn (RincianBiaya $b) => $b->kategori === KategoriBiaya::TransportLokal);
        $daftarRiil = $usulan->daftarRiil->first();
        $transportLokal = $daftarRiil?->rincian ?? collect();
        $total = (float) $rincian->sum('jumlah') + (float) ($daftarRiil?->total_riil ?? 0);

        return [
            'usulan' => $usulan,
            'peserta' => $usulan->peserta->first(),
            'rincian' => $rincian,
            'rincianPerKategori' => $rincian
                ->groupBy(fn (RincianBiaya $b) => $b->kategori->value)
                ->sortBy(fn ($baris, $kategori) => KategoriBiaya::dari($kategori)->urutan()),
            'total' => (float) $rincian->sum('jumlah'),
            'transportLokal' => $transportLokal,
            'totalTransportLokal' => (float) ($daftarRiil?->total_riil ?? 0),
            'totalKeseluruhan' => $total,
            'terbilang' => app(Terbilang::class)->konversi($total),
            'daftarRiil' => $daftarRiil,
            'ppk' => null,
            'bendahara' => null,
            'keuangan' => $usulan->keuangan,
            'tanggalPelaksana' => null,
            'dibayarkan' => 0.0,
            'qrPelaksana' => null,
            'qrPpk' => null,
            'qrBendahara' => null,
        ];
    }
}
