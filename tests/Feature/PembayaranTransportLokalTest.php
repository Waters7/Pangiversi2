<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RiwayatPembayaran;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Penggantian transport lokal dibayarkan dan dicatat sendiri.
 *
 * Nominalnya berasal dari Daftar Pengeluaran Riil — di luar rincian biaya —
 * dan kerap ditransfer terpisah dari pelunasan. Yang belum dibayarkan lewat
 * menu ini tetap ikut pada pelunasan, jadi tidak ada berkas yang tertinggal
 * dan tidak ada nominal yang terbayar dua kali.
 */
class PembayaranTransportLokalTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    private User $ppk;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private Keuangan $keuangan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);
        $this->ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-04-06',
            'tanggal_selesai' => '2026-04-08',
        ]);

        $this->keuangan = Keuangan::factory()->belumBayar()->create([
            'id_usulan' => $this->usulan->id,
            'total' => 5_000_000,
            'uang_muka' => 4_000_000,
            'sisa' => 1_000_000,
        ]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->usulan->id_user,
            'nama' => $this->usulan->user?->nama ?? 'Pelaksana',
            'peran' => 'ketua',
        ]);
    }

    /** Daftar riil yang sudah disahkan PPK, siap diganti. */
    private function riilDitandatangani(float $nominal = 474_500): DaftarRiil
    {
        return DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => $nominal,
            'ditandatangani_at' => now()->subDay(),
            'id_ppk' => $this->ppk->id,
        ]);
    }

    private function bayar(DaftarRiil $daftar, string $tanggal = '2026-04-20'): TestResponse
    {
        return $this->actingAs($this->bendahara)
            ->post(route('pembayaran.bayar-transport', $daftar), [
                'tanggal_bayar' => $tanggal,
                'bukti_bayar' => UploadedFile::fake()->create('transfer.pdf', 40, 'application/pdf'),
            ]);
    }

    // ── Pencatatan ──

    public function test_penggantian_tercatat_beserta_tanggal_dan_buktinya(): void
    {
        $daftar = $this->riilDitandatangani();

        $this->bayar($daftar)->assertSessionHas('success');

        $segar = $daftar->fresh();

        $this->assertTrue($segar->sudahDibayar());
        $this->assertSame('2026-04-20', $segar->dibayar_at->toDateString());
        $this->assertNotNull($segar->bukti_bayar, 'Bukti transfer tidak tersimpan.');
        $this->assertSame($this->bendahara->id, $segar->id_pembayar);
    }

    public function test_penggantian_masuk_jurnal_pembayaran(): void
    {
        $this->bayar($this->riilDitandatangani());

        $baris = RiwayatPembayaran::firstWhere('jenis', RiwayatPembayaran::JENIS_TRANSPORT_LOKAL);

        $this->assertNotNull($baris, 'Penggantian tidak tercatat pada jurnal.');
        $this->assertSame(474_500.0, (float) $baris->nominal);
        $this->assertSame($this->bendahara->id, $baris->id_pencatat);
    }

    public function test_tanggal_dan_bukti_wajib_diisi(): void
    {
        $daftar = $this->riilDitandatangani();

        $this->actingAs($this->bendahara)
            ->post(route('pembayaran.bayar-transport', $daftar), [])
            ->assertSessionHasErrors(['tanggal_bayar', 'bukti_bayar']);

        $this->assertFalse($daftar->fresh()->sudahDibayar());
    }

    // ── Pembatalan ──

    private function batalkan(DaftarRiil $daftar, array $isian = ['alasan' => 'Salah pilih pelaksana, transfer ke rekening lain.']): TestResponse
    {
        return $this->actingAs($this->bendahara)
            ->put(route('pembayaran.batal-transport', $daftar), $isian);
    }

    public function test_pembatalan_mencabut_catatan_dan_masuk_jurnal(): void
    {
        $daftar = $this->riilDitandatangani();
        $this->bayar($daftar);

        $this->batalkan($daftar)->assertSessionHas('success');

        $segar = $daftar->fresh();

        $this->assertFalse($segar->sudahDibayar());
        $this->assertNull($segar->bukti_bayar);
        $this->assertNull($segar->id_pembayar);

        $baris = RiwayatPembayaran::firstWhere('jenis', RiwayatPembayaran::JENIS_BATAL_TRANSPORT_LOKAL);

        $this->assertNotNull($baris, 'Pembatalan tidak tercatat pada jurnal.');
        $this->assertSame(474_500.0, (float) $baris->nominal);
        $this->assertTrue($baris->pembatalan());
        $this->assertStringContainsString('Salah pilih pelaksana', (string) $baris->catatan);

        // Bukti transfer yang lama tetap tersimpan pada baris pembayarannya.
        $this->assertNotNull(RiwayatPembayaran::firstWhere('jenis', RiwayatPembayaran::JENIS_TRANSPORT_LOKAL)->bukti);
    }

    public function test_pembatalan_wajib_beralasan(): void
    {
        $daftar = $this->riilDitandatangani();
        $this->bayar($daftar);

        $this->batalkan($daftar, [])->assertSessionHasErrors('alasan');
        $this->batalkan($daftar, ['alasan' => 'salah'])->assertSessionHasErrors('alasan');

        $this->assertTrue($daftar->fresh()->sudahDibayar());
    }

    public function test_yang_belum_dibayar_tidak_dapat_dibatalkan(): void
    {
        $this->batalkan($this->riilDitandatangani())->assertSessionHas('error');
    }

    public function test_setelah_dibatalkan_dapat_dicatat_ulang(): void
    {
        $daftar = $this->riilDitandatangani();
        $this->bayar($daftar);
        $this->batalkan($daftar);

        $this->bayar($daftar, '2026-04-22')->assertSessionHas('success');

        $this->assertSame('2026-04-22', $daftar->fresh()->dibayar_at->toDateString());
    }

    public function test_halaman_menawarkan_pembatalan_beserta_alasannya(): void
    {
        $daftar = $this->riilDitandatangani();
        $this->bayar($daftar);

        $this->actingAs($this->bendahara)
            ->get(route('pembayaran.transport-lokal'))
            ->assertOk()
            ->assertSee(route('pembayaran.batal-transport', $daftar))
            ->assertSee('Batalkan pencatatan penggantian transport lokal?')
            ->assertSee('Alasan pembatalan');
    }

    // ── Penjagaan ──

    public function test_yang_belum_ditandatangani_ppk_tidak_dapat_dibayar(): void
    {
        $daftar = DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 474_500,
        ]);

        $this->bayar($daftar)->assertForbidden();
    }

    public function test_penggantian_tidak_dapat_dibayar_dua_kali(): void
    {
        $daftar = $this->riilDitandatangani();

        $this->bayar($daftar)->assertSessionHas('success');
        $this->bayar($daftar, '2026-04-25')->assertStatus(422);

        $this->assertSame(
            1,
            RiwayatPembayaran::where('jenis', RiwayatPembayaran::JENIS_TRANSPORT_LOKAL)->count()
        );
    }

    public function test_peran_tanpa_hak_pembayaran_ditolak(): void
    {
        $daftar = $this->riilDitandatangani();

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->post(route('pembayaran.bayar-transport', $daftar), [
                'tanggal_bayar' => '2026-04-20',
                'bukti_bayar' => UploadedFile::fake()->create('transfer.pdf', 40, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    // ── Hubungannya dengan pelunasan ──

    /**
     * Inti perubahannya: satu penggantian tidak boleh terbayar dua kali —
     * sekali lewat menu ini, sekali lagi menumpang pada pelunasan.
     */
    public function test_yang_sudah_dibayar_tidak_ikut_lagi_pada_pelunasan(): void
    {
        $daftar = $this->riilDitandatangani();

        $sebelum = $this->keuangan->fresh()->nilaiPelunasan();
        $this->assertSame(1_474_500.0, $sebelum, 'Penggantian seharusnya ikut pada pelunasan.');

        $this->bayar($daftar);

        $sesudah = $this->usulan->fresh('keuangan')->keuangan->nilaiPelunasan();

        $this->assertSame(1_000_000.0, $sesudah, 'Penggantian yang sudah dibayar masih ikut terhitung.');
    }

    public function test_yang_belum_dibayar_tetap_ikut_pada_pelunasan(): void
    {
        $this->riilDitandatangani();

        $this->assertSame(474_500.0, $this->keuangan->fresh()->reimbursementTransport());
    }

    // ── Tampilan ──

    public function test_menu_menampilkan_yang_menunggu_dan_yang_terbayar(): void
    {
        $daftar = $this->riilDitandatangani();

        $halaman = $this->actingAs($this->bendahara)
            ->get(route('pembayaran.transport-lokal'))
            ->assertOk()
            ->assertSee('Penggantian Transport Lokal')
            ->assertSee('Menunggu Dibayar');

        $this->assertSame(1, $halaman->viewData('jumlah')['menunggu']);

        $this->bayar($daftar);

        $this->actingAs($this->bendahara)
            ->get(route('pembayaran.transport-lokal'))
            ->assertOk()
            ->assertViewHas('jumlah', fn (array $jumlah) => $jumlah['terbayar'] === 1 && $jumlah['menunggu'] === 0);
    }

    public function test_daftar_dikelompokkan_dan_dapat_disaring_per_periode(): void
    {
        $this->riilDitandatangani();

        $this->actingAs($this->bendahara)
            ->get(route('pembayaran.transport-lokal'))
            ->assertOk()
            ->assertSee('April 2026');

        $halaman = $this->actingAs($this->bendahara)
            ->get(route('pembayaran.transport-lokal', ['bulan' => 9]))
            ->assertOk();

        $this->assertSame(0, $halaman->viewData('daftar')->flatten(1)->count());
    }

    public function test_submenu_tersedia_pada_menu_pembayaran(): void
    {
        $this->actingAs($this->bendahara)
            ->get(route('pembayaran'))
            ->assertOk()
            ->assertSee('Transport Lokal')
            ->assertSee(route('pembayaran.transport-lokal'), false);
    }
}
