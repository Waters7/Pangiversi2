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
}
