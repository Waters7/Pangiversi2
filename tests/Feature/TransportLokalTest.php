<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\RincianDaftarRiil;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Transport lokal hidup di Daftar Pengeluaran Riil, bukan di rincian biaya:
 * baris yang dicatat atau dipindah tim keuangan masuk ke sana — tidak lagi
 * tersembunyi — dan nota pelaksana wajib bernominal agar ikut masuk.
 */
class TransportLokalTest extends TestCase
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
        ]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     */
    private function tulisKeuangan(array $ubahan): TestResponse
    {
        return $this->actingAs($this->timKeuangan)->post(route('keuangan.rincian.store', $this->usulan), array_merge([
            'kategori' => KategoriBiaya::TransportLokal->value,
            'komponen' => 'Transport Bandara PP',
            'volume' => 1,
            'satuan' => 'Kali',
            'harga_satuan' => 71_500,
        ], $ubahan));
    }

    /**
     * @param  array<string, mixed>  $ruas
     */
    private function simpanNota(array $ruas): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'nota',
            'ruas' => $ruas,
        ]);
    }

    private function daftarRiil(): ?DaftarRiil
    {
        return DaftarRiil::with('rincian')->firstWhere('id_usulan', $this->usulan->id);
    }

    // ── Dicatat dan dipindah tim keuangan ──

    public function test_transport_lokal_tim_keuangan_dicatat_pada_daftar_riil(): void
    {
        $this->tulisKeuangan([])->assertSessionHas('success', 'Komponen dicatat pada Transport Lokal (Daftar Pengeluaran Riil).');

        $this->assertSame(0, RincianBiaya::count());

        $baris = $this->daftarRiil()->rincian->sole();
        $this->assertSame('Transport Bandara PP', $baris->uraian);
        $this->assertSame(71_500.0, $baris->nominal);
        $this->assertSame(RincianDaftarRiil::SUMBER_MANUAL, $baris->sumber);
        $this->assertSame(71_500.0, (float) $this->daftarRiil()->total_riil);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertSeeInOrder(['Transport Lokal', 'Transport Bandara PP', 'Ditulis tim keuangan', 'Rp 71.500']);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.transport-lokal'))
            ->assertSee('Transport Bandara PP')
            ->assertSee('Ditulis tim keuangan');
    }

    public function test_komponen_transport_yang_dipindah_ke_transport_lokal_tidak_hilang(): void
    {
        $this->tulisKeuangan(['kategori' => KategoriBiaya::Transport->value, 'isian_pelaksana' => 'transport:lain']);
        $baris = RincianBiaya::sole();
        $this->assertSame(71_500.0, (float) $this->usulan->keuangan->fresh()->total);

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.update', [$this->usulan, $baris]), [
                'kategori' => KategoriBiaya::TransportLokal->value,
                'komponen' => 'Transport Bandara PP',
                'volume' => 1,
                'satuan' => 'Kali',
                'harga_satuan' => 71_500,
            ])
            ->assertSessionHas('success');

        $this->assertModelMissing($baris);
        $this->assertSame(0.0, (float) $this->usulan->keuangan->fresh()->total);
        $this->assertSame(71_500.0, (float) $this->daftarRiil()->total_riil);
        $this->assertSame('Transport Bandara PP', $this->daftarRiil()->rincian->sole()->uraian);
    }

    public function test_baris_dari_berkas_pelaksana_tidak_dipindah_ke_transport_lokal(): void
    {
        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'akomodasi',
            'bill_hotel' => UploadedFile::fake()->create('bill.pdf', 10, 'application/pdf'),
            'bill_hotel_no_transaksi' => '001',
            'bill_hotel_nominal' => 1_200_000,
            'kwintasi' => UploadedFile::fake()->create('kw.pdf', 10, 'application/pdf'),
        ]);
        $baris = RincianBiaya::where('kunci_sumber', 'hotel')->sole();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.update', [$this->usulan, $baris]), [
                'kategori' => KategoriBiaya::TransportLokal->value,
                'komponen' => $baris->komponen,
                'volume' => 1,
                'satuan' => 'Kali',
                'harga_satuan' => 1_200_000,
            ])
            ->assertSessionHas('error');

        $this->assertModelExists($baris);
        $this->assertSame(KategoriBiaya::Penginapan, $baris->fresh()->kategori);
    }

    public function test_hanya_transport_lokal_tulisan_tim_keuangan_yang_dapat_dihapus(): void
    {
        $this->tulisKeuangan([]);
        $this->simpanNota([1 => ['nominal' => 50_000, 'bukti' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]]);

        $manual = $this->daftarRiil()->rincian->firstWhere('sumber', RincianDaftarRiil::SUMBER_MANUAL);
        $dariNota = $this->daftarRiil()->rincian->firstWhere('sumber', RincianDaftarRiil::SUMBER_DOKUMEN);

        $this->actingAs($this->timKeuangan)
            ->delete(route('keuangan.transport-lokal.destroy', [$this->usulan, $dariNota->id]))
            ->assertForbidden();

        $this->actingAs($this->timKeuangan)
            ->delete(route('keuangan.transport-lokal.destroy', [$this->usulan, $manual->id]))
            ->assertSessionHas('success');

        $this->assertModelMissing($manual);
        $this->assertSame(50_000.0, (float) $this->daftarRiil()->total_riil);
    }

    // ── Nota pelaksana wajib bernominal ──

    public function test_nota_yang_diunggah_wajib_bernominal(): void
    {
        $this->simpanNota([1 => ['nominal' => '', 'bukti' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('ruas.1.nominal');

        $this->assertSame(0, $this->usulan->notaTransport()->count());

        $this->simpanNota([1 => ['nominal' => 85_000, 'bukti' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]])
            ->assertSessionHasNoErrors();

        $baris = $this->daftarRiil()->rincian->sole();
        $this->assertSame('nota:1', $baris->kunci_sumber);
        $this->assertSame(85_000.0, $baris->nominal);

        // Nota yang sudah tersimpan juga tidak boleh ditinggalkan tanpa nominal.
        $this->simpanNota([1 => ['nominal' => '']])->assertSessionHasErrors('ruas.1.nominal');
    }

    public function test_nota_yang_salah_unggah_dapat_dihapus(): void
    {
        $this->simpanNota([1 => ['nominal' => 85_000, 'bukti' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]]);
        $bukti = $this->usulan->notaTransport()->where('urutan', 1)->sole()->bukti;

        $this->simpanNota([1 => ['nominal' => '', 'hapus' => '1']])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->usulan->notaTransport()->where('urutan', 1)->count());
        Storage::disk('public')->assertMissing($bukti);
        $this->assertCount(0, $this->daftarRiil()->rincian);
    }

    // ── Penyusulan data lama ──

    public function test_baris_transport_lokal_lama_di_rincian_dipindah_ke_daftar_riil(): void
    {
        // Seperti data dari sebelum perbaikan: tersembunyi di rincian biaya.
        RincianBiaya::factory()->create([
            'id_keuangan' => $this->usulan->keuangan->id,
            'kategori' => KategoriBiaya::TransportLokal->value,
            'komponen' => 'Transport Bandara PP',
            'volume' => 1,
            'harga_satuan' => 71_500,
            'jumlah' => 71_500,
        ]);
        $this->usulan->keuangan->hitungTotal();

        $this->artisan('pangi:selaraskan-biaya')
            ->expectsOutputToContain('"Transport Bandara PP" dipindah ke transport lokal')
            ->assertSuccessful();

        $this->assertSame(0, RincianBiaya::count());
        $this->assertSame(0.0, (float) $this->usulan->keuangan->fresh()->total);
        $this->assertSame(71_500.0, (float) $this->daftarRiil()->total_riil);
    }

    public function test_nota_bernominal_yang_belum_masuk_daftar_riil_disusulkan(): void
    {
        $this->simpanNota([1 => ['nominal' => 85_000, 'bukti' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]]);

        // Seperti di peladen sebelum perbaikan: penyalinannya terhenti.
        RincianDaftarRiil::query()->delete();

        $this->artisan('pangi:selaraskan-biaya')->assertSuccessful();

        $this->assertSame(85_000.0, $this->daftarRiil()->rincian->sole()->nominal);
    }
}
