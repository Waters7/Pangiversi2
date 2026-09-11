<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pembagian kerja pada menu Keuangan, dan laporan berkas yang sudah tuntas.
 *
 * Menyusun angka dan menyatakannya benar adalah dua pekerjaan berbeda:
 * tim keuangan memvalidasi, bendahara membayar berdasarkan angka itu.
 * Perjadin yang sudah selesai tetap terbuka di menu Keuangan, dan rincian
 * biaya yang tanda tangannya lengkap punya laporannya sendiri.
 */
class RevisiMenuKeuanganTest extends TestCase
{
    use RefreshDatabase;

    private User $timKeuangan;

    private User $bendahara;

    private User $ppk;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
        $this->bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);
        $this->ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.03.01/F.XXXVIII/88/2026',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->usulan->id_user,
            'nama' => $this->usulan->user?->nama ?? 'Pelaksana',
            'peran' => 'ketua',
        ]);
    }

    private function rincianDokumen(): RincianBiaya
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        return $this->usulan->fresh('keuangan.rincianBiaya')->keuangan->rincianBiaya
            ->firstWhere('sumber', RincianBiaya::SUMBER_DOKUMEN);
    }

    // ── Validasi bukan pekerjaan bendahara ──

    public function test_tim_keuangan_dapat_memvalidasi_nominal(): void
    {
        $baris = $this->rincianDokumen();

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $baris]))
            ->assertSessionHas('success');

        $this->assertNotNull($baris->fresh()->divalidasi_at);
    }

    public function test_bendahara_tidak_dapat_memvalidasi_nominal(): void
    {
        $baris = $this->rincianDokumen();

        $this->actingAs($this->bendahara)
            ->put(route('keuangan.rincian.validasi', [$this->usulan, $baris]))
            ->assertForbidden();

        $this->assertNull($baris->fresh()->divalidasi_at, 'Bendahara berhasil memvalidasi, seharusnya tidak.');
    }

    public function test_bendahara_tidak_dapat_mencabut_validasi(): void
    {
        $baris = $this->rincianDokumen();
        $baris->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        $this->actingAs($this->bendahara)
            ->delete(route('keuangan.rincian.batal-validasi', [$this->usulan, $baris]))
            ->assertForbidden();

        $this->assertNotNull($baris->fresh()->divalidasi_at);
    }

    public function test_tombol_validasi_tidak_tampil_bagi_bendahara(): void
    {
        $this->rincianDokumen();

        $this->actingAs($this->bendahara)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertDontSee('Validasi komponen ini?');
    }

    public function test_tombol_validasi_tampil_bagi_tim_keuangan(): void
    {
        $this->rincianDokumen();

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('Validasi komponen ini?')
            ->assertSee('Apakah Anda yakin nominalnya sudah benar diperiksa?');
    }

    // ── Perjadin selesai tetap terbuka di menu Keuangan ──

    public function test_perjadin_selesai_ikut_tampil_dan_dapat_disaring(): void
    {
        $selesai = Usulan::factory()->create(['status' => StatusUsulan::Selesai->value]);
        Keuangan::factory()->lunas()->create(['id_usulan' => $selesai->id]);

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee('Selesai');

        $this->assertSame(1, $halaman->viewData('jumlahStatus')['selesai']);
        $this->assertSame(1, $halaman->viewData('jumlahStatus')['berjalan']);

        $disaring = $this->actingAs($this->timKeuangan)
            ->get(route('keuangan', ['status' => 'selesai']))
            ->assertOk();

        $this->assertSame(1, $disaring->viewData('usulan')->total());
        $this->assertTrue($disaring->viewData('usulan')->contains($selesai));
    }

    // ── Laporan rincian biaya lengkap tanda tangan ──

    /** Berkas dengan kedua tanda tangan PPK dan nominatif yang sudah dikirim. */
    private function berkasLengkap(): DaftarRiil
    {
        $daftar = DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now()->subDay(),
            'id_ppk' => $this->ppk->id,
            'rincian_ditandatangani_at' => now(),
            'rincian_id_ppk' => $this->ppk->id,
        ]);

        DaftarNominatif::create([
            'no_tugas' => $this->usulan->no_tugas,
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);

        return $daftar;
    }

    public function test_laporan_memuat_berkas_yang_lengkap_tanda_tangannya(): void
    {
        $this->berkasLengkap();

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap'))
            ->assertOk()
            ->assertSee('Rincian Biaya Lengkap Tanda Tangan')
            ->assertSee($this->usulan->no_usulan);

        $this->assertSame(1, $halaman->viewData('jumlahBerkas'));
    }

    public function test_berkas_yang_belum_lengkap_tidak_ikut(): void
    {
        // Hanya daftar riil yang ditandatangani; rincian biayanya belum.
        DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now(),
            'id_ppk' => $this->ppk->id,
        ]);

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap'))
            ->assertOk();

        $this->assertSame(0, $halaman->viewData('jumlahBerkas'));
    }

    /**
     * Daftar nominatif tidak ditunggu: rincian yang sudah disahkan PPK
     * sudah terkunci, jadi ia diarsipkan meski nominatifnya belum dikirim —
     * atau belum terbit sama sekali.
     */
    public function test_berkas_ikut_meski_nominatif_belum_terkirim(): void
    {
        DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now(),
            'id_ppk' => $this->ppk->id,
            'rincian_ditandatangani_at' => now(),
            'rincian_id_ppk' => $this->ppk->id,
        ]);

        DaftarNominatif::create([
            'no_tugas' => $this->usulan->no_tugas,
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
        ]);

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan);

        $this->assertSame(1, $halaman->viewData('jumlahBerkas'));
    }

    public function test_berkas_ikut_meski_nominatif_belum_terbit(): void
    {
        DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now(),
            'id_ppk' => $this->ppk->id,
            'rincian_ditandatangani_at' => now(),
            'rincian_id_ppk' => $this->ppk->id,
        ]);

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap'))
            ->assertOk();

        $this->assertSame(1, $halaman->viewData('jumlahBerkas'));
    }

    public function test_laporan_dikelompokkan_dan_dapat_disaring_per_periode(): void
    {
        $this->berkasLengkap();

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap', ['tahun' => now()->year - 1]))
            ->assertOk();

        $this->assertSame(0, $halaman->viewData('jumlahBerkas'));

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap'))
            ->assertOk()
            ->assertSee(now()->translatedFormat('F Y'));
    }
}
