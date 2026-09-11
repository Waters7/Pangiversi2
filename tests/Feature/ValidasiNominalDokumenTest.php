<?php

namespace Tests\Feature;

use App\Enums\ArahTiket;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Alur nominal dari pelaksana sampai siap ditandatangani:
 * diisi pelaksana → divalidasi tim keuangan → dikirim ke Rincian Saya untuk
 * masa sanggah → disetujui → ditandatangani.
 *
 * Yang dijaga di sini adalah pintu tengahnya: rincian tidak boleh sampai ke
 * pelaksana untuk disetujui selama angkanya belum diperiksa siapa pun.
 */
class ValidasiNominalDokumenTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $timKeuangan;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

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

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);
    }

    /**
     * Pelaksana mengisi satu nominal lewat berkas pertanggungjawaban.
     */
    private function isiNominal(float $harga = 2_450_000): void
    {
        $this->actingAs($this->pelaksana)->post(route('dokumen.store', $this->usulan), [
            'section' => 'tiket',
            'arah' => ArahTiket::Pergi->value,
            'kota_asal' => 'Manado',
            'kota_tujuan' => 'Jakarta',
            'nomor_tiket' => 'GA-602',
            'kode_booking' => 'XY7QW2',
            'harga' => $harga,
            'boarding_pass' => UploadedFile::fake()->create('bp.pdf', 50, 'application/pdf'),
            'invoice' => UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf'),
        ]);
    }

    private function rincian(): RincianBiaya
    {
        return $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->firstOrFail();
    }

    /**
     * Nominal daftar riil lahir dari nota transportasi pelaksana.
     */
    private function siapkanDaftarRiil(): void
    {
        $this->usulan->notaTransport()->updateOrCreate(['urutan' => 1], ['nominal' => 450_000]);

        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
    }

    private function kirimKePelaksana(): TestResponse
    {
        // Transport lokal diperiksa terpisah dari rincian biaya.
        DaftarRiil::where('id_usulan', $this->usulan->id)
            ->whereNull('divalidasi_at')
            ->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        // Tim keuangan yang memeriksa nominal lalu mengirimkan kedua dokumen.
        return $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));
    }

    // ── Pintu validasi ──

    public function test_rincian_belum_dapat_dikirim_selama_nominal_belum_divalidasi(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->kirimKePelaksana()->assertSessionHas('error');

        $this->assertNull($this->usulan->daftarRiil()->first()?->dikirim_ke_pegawai_at);
    }

    public function test_pesan_penolakan_menyebut_jumlah_yang_belum_diperiksa(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->kirimKePelaksana()
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'belum divalidasi'));
    }

    public function test_rincian_dapat_dikirim_setelah_seluruh_nominal_divalidasi(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]));

        $this->kirimKePelaksana()->assertSessionHas('success');

        $this->assertNotNull($this->usulan->daftarRiil()->first()->dikirim_ke_pegawai_at);
    }

    /**
     * Baris yang diketik sendiri tim keuangan tidak perlu divalidasi ulang —
     * penulisnya sudah pihak yang berwenang.
     */
    public function test_baris_dari_tim_keuangan_tidak_menahan_pengiriman(): void
    {
        $this->actingAs($this->timKeuangan)->post(route('keuangan.rincian.store', $this->usulan), [
            'kategori' => 'uang_harian',
            'komponen' => 'Uang harian',
            'volume' => 3,
            'satuan' => 'OH',
            'harga_satuan' => 530_000,
        ]);

        $this->siapkanDaftarRiil();

        $this->kirimKePelaksana()->assertSessionHas('success');
    }

    public function test_pelaksana_diberi_tahu_untuk_masa_sanggah(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]));

        $this->kirimKePelaksana();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Berkas pertanggungjawaban menunggu tanda tangan Anda',
        ]);
    }

    /**
     * Nominal yang diubah pelaksana setelah divalidasi menutup pintunya lagi.
     */
    public function test_nominal_yang_diubah_menahan_pengiriman_kembali(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]));

        // Pelaksana memperbaiki angkanya.
        $this->isiNominal(9_000_000);

        $this->kirimKePelaksana()->assertSessionHas('error');
    }

    // ── Pembantu layanan ──

    public function test_layanan_melaporkan_baris_yang_menunggu_validasi(): void
    {
        $this->isiNominal();

        $sinkron = app(SinkronBiayaDokumen::class);
        $usulan = $this->usulan->fresh('keuangan');

        $this->assertCount(1, $sinkron->menungguValidasi($usulan));
        $this->assertFalse($sinkron->seluruhnyaTervalidasi($usulan));

        $this->rincian()->update(['divalidasi_at' => now()]);

        $this->assertTrue($sinkron->seluruhnyaTervalidasi($this->usulan->fresh('keuangan')));
    }
    // ── Berkas berjalan sendiri begitu pemeriksaan rampung ──

    /**
     * Dulu tim keuangan harus menekan tombol kirim terpisah setelah semua
     * divalidasi; berkas kerap berhenti di "sudah dicek" dan tombol tanda
     * tangan pelaksana tidak pernah muncul.
     */
    public function test_validasi_terakhir_langsung_mengirim_berkas_ke_pelaksana(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        // Transport lokal sudah diperiksa lebih dulu; rincian inilah yang terakhir.
        DaftarRiil::where('id_usulan', $this->usulan->id)
            ->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]))
            ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, 'dikirim ke pelaksana'));

        $daftar = $this->usulan->daftarRiil()->first();

        $this->assertNotNull($daftar->dikirim_ke_pegawai_at);
        $this->assertNotNull($daftar->batas_sanggah);
        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Berkas pertanggungjawaban menunggu tanda tangan Anda',
        ]);
    }

    public function test_validasi_transport_terakhir_juga_mengirim_berkas(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]));

        $this->assertNull($this->usulan->daftarRiil()->first()->dikirim_ke_pegawai_at);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.validasi', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, 'dikirim ke'));

        $this->assertNotNull($this->usulan->daftarRiil()->first()->dikirim_ke_pegawai_at);
    }

    /** Validasi yang belum menuntaskan semuanya tidak mengirim apa pun. */
    public function test_validasi_sebagian_belum_mengirim(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]))
            ->assertSessionHas('success', fn (string $pesan) => ! str_contains($pesan, 'dikirim'));

        $this->assertNull($this->usulan->daftarRiil()->first()->dikirim_ke_pegawai_at);
    }

    /** Berkas yang sudah di meja pelaksana tidak dikirim ulang diam-diam saat divalidasi lagi. */
    public function test_validasi_ulang_tidak_mengirim_ulang(): void
    {
        $this->isiNominal();
        $this->siapkanDaftarRiil();
        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]));
        $this->kirimKePelaksana();

        $dikirim = $this->usulan->daftarRiil()->first()->dikirim_ke_pegawai_at;

        $this->rincian()->update(['divalidasi_at' => null]);
        $this->travel(1)->hours();
        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $this->rincian()]));

        $this->assertEquals($dikirim, $this->usulan->daftarRiil()->first()->dikirim_ke_pegawai_at);
    }
}
