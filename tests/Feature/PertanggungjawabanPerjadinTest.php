<?php

namespace Tests\Feature;

use App\Enums\ArahTiket;
use App\Enums\KategoriBiaya;
use App\Enums\RuasTransport;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Berkas pertanggungjawaban kini merekam angkanya, bukan hanya menyimpan
 * pindaian: tiket per arah, nota transportasi per ruas, dan rincian bill
 * hotel. Nominalnya mengalir sendiri ke rincian biaya untuk divalidasi.
 */
class PertanggungjawabanPerjadinTest extends TestCase
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
    private function simpanTiket(ArahTiket $arah, array $ubahan = []): TestResponse
    {
        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), array_merge([
            'section' => 'tiket',
            'arah' => $arah->value,
            'kota_asal' => 'Manado',
            'kota_tujuan' => 'Jakarta',
            'nomor_tiket' => 'GA-602',
            'kode_booking' => 'XY7QW2',
            'harga' => 2_450_000,
            'boarding_pass' => $this->berkas('bp.pdf'),
            'invoice' => $this->berkas('invoice.pdf'),
        ], $ubahan));
    }

    /**
     * @param  array<int, float>  $nominal
     */
    private function simpanNota(array $nominal): TestResponse
    {
        $ruas = [];

        // Ruas yang bernominal wajib bernota, jadi buktinya ikut dikirim.
        foreach (RuasTransport::urutan() as $satu) {
            $ruas[$satu->value] = ['nominal' => $nominal[$satu->value] ?? null];

            if (($nominal[$satu->value] ?? 0) > 0) {
                $ruas[$satu->value]['bukti'] = $this->berkas("nota-{$satu->value}.pdf");
            }
        }

        return $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'nota',
            'ruas' => $ruas,
        ]);
    }

    // ── SPPD ──

    public function test_formulir_mengingatkan_hardcopy_sppd_dikumpulkan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('SPPD yang sudah ditandatangani lengkap')
            ->assertSee('Hardcopy SPPD tetap dikumpulkan ke Tim Keuangan');
    }

    // ── Tiket ──

    public function test_tiket_pergi_dan_pulang_disimpan_terpisah(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pulang, [
            'kota_asal' => 'Jakarta',
            'kota_tujuan' => 'Manado',
            'nomor_tiket' => 'GA-603',
            'kode_booking' => 'ZZ1AA2',
            'harga' => 2_600_000,
        ]);

        $tiket = $this->usulan->tiket()->get()->keyBy(fn ($t) => $t->arah->value);

        $this->assertCount(2, $tiket);
        $this->assertSame('GA-602', $tiket['pergi']->nomor_tiket);
        $this->assertSame('Jakarta', $tiket['pulang']->kota_asal);
        $this->assertSame(2_600_000.0, $tiket['pulang']->harga);
    }

    public function test_tiap_tiket_punya_boarding_pass_sendiri(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pulang);

        $berkas = $this->usulan->tiket()->pluck('boarding_pass');

        $this->assertCount(2, $berkas->filter());
        $this->assertSame(2, $berkas->unique()->count(), 'Boarding pass tiap arah harus berkas sendiri.');
    }

    public function test_mengirim_ulang_tiket_memperbarui_baris_yang_sama(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pergi, ['harga' => 3_000_000]);

        $this->assertSame(1, $this->usulan->tiket()->count());
        $this->assertSame(3_000_000.0, $this->usulan->tiket()->first()->harga);
    }

    public function test_tiket_tanpa_kode_booking_ditolak(): void
    {
        $this->simpanTiket(ArahTiket::Pergi, ['kode_booking' => ''])
            ->assertSessionHasErrors('kode_booking');

        $this->assertSame(0, $this->usulan->tiket()->count());
    }

    public function test_tiket_tanpa_harga_ditolak(): void
    {
        $this->simpanTiket(ArahTiket::Pergi, ['harga' => null])
            ->assertSessionHasErrors('harga');
    }

    // ── Nota transportasi ──

    public function test_empat_ruas_transportasi_tersimpan_berurutan(): void
    {
        $this->simpanNota([1 => 75_000, 2 => 150_000, 3 => 150_000, 4 => 75_000]);

        $nota = $this->usulan->notaTransport()->get();

        $this->assertCount(4, $nota);
        $this->assertSame([1, 2, 3, 4], $nota->pluck('urutan')->all());
        $this->assertSame('Rumah ke bandara', $nota->first()->label);
    }

    public function test_total_biaya_transportasi_dijumlahkan(): void
    {
        $this->simpanNota([1 => 75_000, 2 => 150_000, 3 => 150_000, 4 => 75_000]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertViewHas('totalNota', 450_000.0);
    }

    public function test_ruas_yang_dikosongkan_tidak_dihitung(): void
    {
        $this->simpanNota([1 => 75_000]);

        $this->assertSame(75_000.0, (float) $this->usulan->notaTransport()->sum('nominal'));
    }

    // ── Bill hotel ──

    public function test_bill_hotel_menyimpan_nomor_transaksi_dan_nominal(): void
    {
        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'akomodasi',
            'bill_hotel' => $this->berkas('bill.pdf'),
            'bill_hotel_no_transaksi' => 'TRX-88192',
            'bill_hotel_nominal' => 1_200_000,
            'kwintasi' => $this->berkas('kwitansi.pdf'),
        ])->assertRedirect();

        $dokumen = $this->usulan->fresh('dokumen')->dokumen->last();

        $this->assertSame('TRX-88192', $dokumen->bill_hotel_no_transaksi);
        $this->assertSame(1_200_000.0, $dokumen->bill_hotel_nominal);
    }

    // ── Sinkronisasi ke rincian biaya ──

    public function test_nominal_tiket_masuk_ke_rincian_biaya(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);

        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->first();

        $this->assertNotNull($rincian);
        $this->assertSame(KategoriBiaya::Transport, $rincian->kategori);
        $this->assertSame(2_450_000.0, $rincian->jumlah);

        // Nama komponennya membawa kode booking, supaya dokumen rincian biaya
        // dapat ditautkan ke tiketnya tanpa membuka aplikasi.
        $this->assertStringStartsWith('Tiket Pergi', $rincian->komponen);
        $this->assertStringContainsString('Kode Booking', $rincian->komponen);
    }

    /**
     * Biaya transport lokal dipertanggungjawabkan lewat Daftar Pengeluaran
     * Riil, bukan lewat rincian biaya: ia dinyatakan sendiri oleh
     * pelaksana, bukan ditagihkan dengan kuitansi resmi.
     */
    public function test_nota_transportasi_masuk_ke_daftar_riil_bukan_rincian_biaya(): void
    {
        $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);

        $this->simpanNota([2 => 150_000]);

        $this->assertSame(0, $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->count());

        $daftar = DaftarRiil::firstWhere('id_usulan', $this->usulan->id);

        $this->assertNotNull($daftar);
        $this->assertSame('Bandara ke lokasi tujuan perjadin', $daftar->rincian->first()->uraian);
        $this->assertSame(150_000.0, $daftar->total_riil);
    }

    /**
     * Angka dari pelaksana belum boleh dibayarkan sebelum diperiksa. Tanpa
     * penanda ini, tidak ada bedanya dengan angka yang sudah disetujui.
     */
    public function test_nominal_dari_dokumen_menunggu_validasi(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);

        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->first();

        $this->assertSame(RincianBiaya::SUMBER_DOKUMEN, $rincian->sumber);
        $this->assertNull($rincian->divalidasi_at);
        $this->assertFalse($rincian->sudahDivalidasi());
    }

    public function test_mengubah_nominal_tidak_menambah_baris_baru(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pergi, ['harga' => 2_700_000]);

        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->get();

        $this->assertCount(1, $rincian);
        $this->assertSame(2_700_000.0, $rincian->first()->jumlah);
    }

    /**
     * Nominal yang sudah divalidasi lalu diubah pelaksana harus diperiksa
     * ulang — kalau tidak, kenaikan angka lolos tanpa dilihat siapa pun.
     */
    public function test_validasi_dicabut_saat_nominalnya_berubah(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);

        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->first();
        $rincian->update(['divalidasi_at' => now()]);

        $this->simpanTiket(ArahTiket::Pergi, ['harga' => 9_000_000]);

        $this->assertNull($rincian->fresh()->divalidasi_at);
    }

    public function test_nominal_yang_dikosongkan_dicabut_dari_rincian(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pulang);
        $this->assertSame(2, $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->count());

        $this->usulan->tiket()->where('arah', ArahTiket::Pulang->value)->update(['harga' => 0]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->assertSame(1, $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->count());
    }

    /**
     * Total keuangan hanya memuat yang masuk rincian biaya; nota
     * transport lokal tidak ikut karena tempatnya di daftar riil.
     */
    public function test_total_keuangan_ikut_terhitung_ulang(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanNota([1 => 50_000]);

        $this->assertSame(2_450_000.0, $this->usulan->fresh('keuangan')->keuangan->total);
    }

    // ── Validasi tim keuangan ──

    public function test_tim_keuangan_dapat_memvalidasi_nominal(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->first();

        $timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $this->actingAs($timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $rincian]))
            ->assertRedirect();

        $rincian->refresh();

        $this->assertNotNull($rincian->divalidasi_at);
        $this->assertSame($timKeuangan->id, $rincian->id_validator);
        $this->assertTrue($rincian->sudahDivalidasi());
    }

    public function test_pelaksana_tidak_dapat_memvalidasi_nominalnya_sendiri(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->first();

        $this->actingAs($this->pelaksana)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $rincian]))
            ->assertForbidden();

        $this->assertNull($rincian->fresh()->divalidasi_at);
    }

    public function test_validasi_dapat_dicabut_kembali(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $rincian = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->first();
        $rincian->update(['divalidasi_at' => now()]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]))
            ->delete(route('keuangan.rincian.batal-validasi', [$this->usulan, $rincian]));

        $this->assertNull($rincian->fresh()->divalidasi_at);
    }

    // ── Batas waktu ──

    public function test_formulir_menyebut_batas_h_plus_tiga(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Wajib diselesaikan paling lambat H+3 setelah perjalanan berakhir')
            ->assertSee('pelunasan sisa 20% tertahan');
    }

    public function test_kelengkapan_yang_masih_kurang_ditampilkan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Tiket Pergi')
            ->assertSee('Laporan perjalanan dinas');
    }
    // ── Seksi akomodasi tidak menagih kolom yang tidak ada di formulir ──

    /**
     * Formulir akomodasi tidak memuat kolom faktur — satuan kerja tidak
     * menerbitkannya — tetapi aturannya sempat tertinggal sehingga seksi ini
     * menolak dengan "The faktur field is required" tanpa kolom yang bisa diisi.
     */
    public function test_akomodasi_tersimpan_tanpa_faktur(): void
    {
        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'akomodasi',
            'bill_hotel' => $this->berkas('bill.pdf'),
            'bill_hotel_no_transaksi' => 'TRX-1',
            'bill_hotel_nominal' => 500_000,
            'kwintasi' => $this->berkas('kwitansi.pdf'),
        ])->assertSessionDoesntHaveErrors()->assertRedirect();

        $this->assertNotNull($this->usulan->dokumen()->latest('id')->value('bill_hotel'));
    }

    public function test_formulir_akomodasi_tidak_memuat_kolom_faktur(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertDontSee('name="faktur"', false);
    }

    // ── Invoice tiket per arah ──

    public function test_tiap_tiket_meminta_invoice_pembeliannya(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Invoice tiket pergi')
            ->assertSee('Invoice tiket pulang');
    }

    public function test_tiket_tanpa_invoice_ditolak(): void
    {
        $this->simpanTiket(ArahTiket::Pergi, ['invoice' => null])
            ->assertSessionHasErrors('invoice');

        $this->assertSame(0, $this->usulan->tiket()->count());
    }

    public function test_invoice_tersimpan_per_arah(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->simpanTiket(ArahTiket::Pulang);

        $invoice = $this->usulan->tiket()->pluck('invoice');

        $this->assertCount(2, $invoice->filter());
        $invoice->each(fn (string $berkas) => Storage::disk('public')->assertExists($berkas));
    }

    /** Invoice yang sudah tersimpan tidak ditagih lagi saat tiket dikirim ulang. */
    public function test_invoice_tersimpan_tidak_ditagih_ulang(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);

        $this->simpanTiket(ArahTiket::Pergi, ['invoice' => null, 'boarding_pass' => null, 'harga' => 2_600_000])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2_600_000.0, (float) $this->usulan->tiket()->value('harga'));
    }

    public function test_tiket_belum_lengkap_tanpa_invoice(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->usulan->tiket()->update(['invoice' => null]);

        $this->assertFalse($this->usulan->tiket()->first()->lengkap());
    }

    // ── Nota transportasi hanya wajib bila bernominal ──

    public function test_ruas_bernominal_wajib_bernota(): void
    {
        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'nota',
            'ruas' => [
                1 => ['nominal' => 100_000],
                2 => ['nominal' => null],
                3 => ['nominal' => null],
                4 => ['nominal' => null],
            ],
        ])->assertSessionHasErrors('ruas.1.bukti');

        $this->assertSame(0, $this->usulan->notaTransport()->count());
    }

    public function test_ruas_tanpa_nominal_tidak_wajib_bernota(): void
    {
        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'nota',
            'ruas' => [
                1 => ['nominal' => 100_000, 'bukti' => $this->berkas('nota-1.pdf')],
                2 => ['nominal' => null],
                3 => ['nominal' => null],
                4 => ['nominal' => null],
            ],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(100_000.0, (float) $this->usulan->notaTransport()->sum('nominal'));
    }

    /** Nota yang sudah pernah diunggah tidak ditagih lagi saat nominalnya diubah. */
    public function test_nota_tersimpan_tidak_ditagih_ulang(): void
    {
        $this->simpanNota([1 => 100_000]);

        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'nota',
            'ruas' => [
                1 => ['nominal' => 125_000],
                2 => ['nominal' => null],
                3 => ['nominal' => null],
                4 => ['nominal' => null],
            ],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(125_000.0, (float) $this->usulan->notaTransport()->sum('nominal'));
    }

    // ── Checklist kelengkapan mengikuti isi formulir ──

    public function test_checklist_tiket_membawa_boarding_pass_dan_invoice(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);

        $baris = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()))
            ->firstWhere('label', 'Tiket Pergi');

        $this->assertTrue($baris['terpenuhi']);
        $this->assertSame(['Boarding pass', 'Invoice'], array_column($baris['berkas'], 'label'));
        $this->assertSame('Kode booking XY7QW2', $baris['catatan']);
    }

    public function test_checklist_tiket_menyebut_bagian_yang_kurang(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $this->usulan->tiket()->update(['invoice' => null]);

        $baris = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()))
            ->firstWhere('label', 'Tiket Pergi');

        $this->assertFalse($baris['terpenuhi']);
        $this->assertSame('Belum ada invoice', $baris['catatan']);
    }

    public function test_checklist_nota_membawa_bukti_tiap_ruas(): void
    {
        $this->simpanNota([1 => 100_000, 3 => 50_000]);

        $baris = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()))
            ->firstWhere('label', 'Nota Transportasi Lokal');

        $this->assertTrue($baris['terpenuhi']);
        $this->assertSame(['Ruas 1', 'Ruas 3'], array_column($baris['berkas'], 'label'));
    }

    public function test_checklist_nota_menandai_ruas_bernominal_tanpa_bukti(): void
    {
        $this->simpanNota([1 => 100_000]);
        $this->usulan->notaTransport()->update(['bukti' => null]);

        $baris = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()))
            ->firstWhere('label', 'Nota Transportasi Lokal');

        $this->assertFalse($baris['terpenuhi']);
        $this->assertStringContainsString('ruas 1', $baris['catatan']);
    }

    public function test_checklist_keuangan_menautkan_tiap_berkas_tiket(): void
    {
        $this->simpanTiket(ArahTiket::Pergi);
        $tiket = $this->usulan->tiket()->first();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]))
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee(route('berkas.lihat', $tiket->boarding_pass))
            ->assertSee(route('berkas.lihat', $tiket->invoice));
    }
}
