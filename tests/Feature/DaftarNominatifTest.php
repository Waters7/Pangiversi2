<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Daftar nominatif mengesahkan pembayaran seluruh pelaksana di bawah satu
 * surat tugas sekaligus. Karena itu ia terbit per surat tugas, bukan per
 * usulan, dan baru setelah semua pelaksananya menyelesaikan dokumen.
 */
class DaftarNominatifTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/12/2026';

    private User $ppk;

    private User $timSdm;

    private User $timKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppk = User::factory()->ppk()->create();
        $this->timSdm = User::factory()->create(['role' => User::ROLE_TIM_SDM]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
    }

    private function usulanLengkap(?User $pelaksana = null): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => ($pelaksana ?? User::factory()->create())->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        $usulan = $this->lengkapiPertanggungjawaban($usulan);

        // Nominal pada dokumen baru menjadi rincian biaya setelah diselaraskan.
        app(SinkronBiayaDokumen::class)->selaraskan($usulan);

        // Nominatif hanya terbit setelah kedua dokumen ditandatangani
        // pelaksana lalu PPK.
        $this->tandatanganiBerkas($usulan->fresh(), $this->ppk);

        return $usulan->fresh();
    }

    private function penyusun(): PenyusunNominatif
    {
        return app(PenyusunNominatif::class);
    }

    // ── Syarat terbit ──

    public function test_terbit_ketika_seluruh_pelaksana_menyelesaikan_dokumen(): void
    {
        $this->usulanLengkap();

        $this->assertTrue($this->penyusun()->siapTerbit(self::NO_TUGAS));
    }

    /**
     * Satu pelaksana yang belum lengkap menahan seluruh daftar: nominatif
     * mengesahkan pembayaran mereka sekaligus, bukan satu per satu.
     */
    public function test_belum_terbit_bila_satu_pelaksana_belum_lengkap(): void
    {
        $this->usulanLengkap();

        Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->assertFalse($this->penyusun()->siapTerbit(self::NO_TUGAS));
    }

    public function test_surat_tugas_tanpa_usulan_tidak_terbit(): void
    {
        $this->assertFalse($this->penyusun()->siapTerbit('KP.03.01/TIDAK-ADA/2026'));
    }

    /**
     * Calon dijaring lewat kueri lebih dulu agar surat tugas tidak ditarik
     * satu per satu. Saringan itu harus longgar: yang layak tidak boleh
     * terlewat hanya karena penjaringannya keliru.
     */
    public function test_penjaringan_calon_menemukan_yang_layak_terbit(): void
    {
        $this->usulanLengkap();

        $this->assertContains(self::NO_TUGAS, $this->penyusun()->suratTugasSiap()->all());
    }

    /** Tanda tangan pada satu jalur saja belum cukup — keduanya dasar nominatif. */
    public function test_berkas_yang_baru_satu_jalur_ditandatangani_belum_terjaring(): void
    {
        $usulan = $this->usulanLengkap();

        $usulan->daftarRiil()->update(['rincian_ditandatangani_at' => null]);

        $this->assertNotContains(self::NO_TUGAS, $this->penyusun()->suratTugasSiap()->all());
        $this->assertFalse($this->penyusun()->siapTerbit(self::NO_TUGAS));
    }

    /** Surat tugas yang daftarnya sudah terbit tidak dijaring ulang. */
    public function test_yang_sudah_terbit_tidak_dijaring_lagi(): void
    {
        $this->usulanLengkap();

        $this->penyusun()->terbitkanYangSiap();

        $this->assertNotContains(self::NO_TUGAS, $this->penyusun()->suratTugasSiap()->all());
    }

    public function test_daftar_terbit_sekali_saja(): void
    {
        $this->usulanLengkap();

        $this->penyusun()->terbitkanYangSiap();
        $this->penyusun()->terbitkanYangSiap();

        $this->assertSame(1, DaftarNominatif::where('no_tugas', self::NO_TUGAS)->count());
    }

    // ── Isi barisnya ──

    public function test_baris_memuat_satu_pelaksana_per_usulan(): void
    {
        $this->usulanLengkap();
        $this->usulanLengkap();

        $baris = $this->penyusun()->baris(self::NO_TUGAS);

        $this->assertCount(2, $baris);
        $this->assertSame(1, $baris->first()['nomor']);
        $this->assertSame(2, $baris->last()['nomor']);
    }

    public function test_totalnya_menjumlahkan_seluruh_baris(): void
    {
        $this->usulanLengkap();
        $this->usulanLengkap();

        $baris = $this->penyusun()->baris(self::NO_TUGAS);
        $total = $this->penyusun()->total($baris);

        $this->assertSame($baris->sum('jumlah'), $total['jumlah']);
    }

    // ── Meja kerja PPK ──

    public function test_ppk_melihat_daftar_yang_terbit(): void
    {
        $this->usulanLengkap();

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif'))
            ->assertOk()
            ->assertSee(self::NO_TUGAS)
            ->assertSee('Menunggu Tanda Tangan PPK');
    }

    public function test_menu_nominatif_tertutup_bagi_peran_lain(): void
    {
        $this->actingAs($this->timSdm)
            ->get(route('persetujuan.nominatif'))
            ->assertForbidden();
    }

    public function test_ppk_menandatangani_daftar(): void
    {
        $this->usulanLengkap();
        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.nominatif.tanda-tangan', $daftar))
            ->assertSessionHas('success');

        $daftar->refresh();

        $this->assertNotNull($daftar->ditandatangani_at);
        $this->assertSame($this->ppk->id, $daftar->id_ppk);
    }

    /**
     * Mengirim tanpa tanda tangan akan membuat tim keuangan memproses berkas
     * yang belum sah.
     */
    public function test_tidak_dapat_dikirim_sebelum_ditandatangani(): void
    {
        $this->usulanLengkap();
        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.nominatif.kirim', $daftar))
            ->assertSessionHas('error');

        $this->assertNull($daftar->fresh()->dikirim_at);
    }

    /**
     * Tim keuangan yang memprosesnya menjadi pembayaran, jadi merekalah
     * yang diberi tahu — bukan Tim SDM, yang hanya mengarsipkan.
     */
    public function test_pengiriman_memberi_tahu_tim_keuangan(): void
    {
        $this->usulanLengkap();
        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);
        $daftar->update(['id_ppk' => $this->ppk->id, 'ditandatangani_at' => now()]);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.nominatif.kirim', $daftar))
            ->assertSessionHas('success');

        $this->assertNotNull($daftar->fresh()->dikirim_at);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->timKeuangan->id,
            'judul' => 'Daftar nominatif diterima dari PPK',
        ]);

        $this->assertDatabaseMissing('notifikasi', [
            'id_user' => $this->timSdm->id,
            'judul' => 'Daftar nominatif diterima dari PPK',
        ]);
    }

    // ── Arsip Tim SDM ──

    public function test_tim_sdm_hanya_melihat_yang_sudah_dikirim(): void
    {
        $this->usulanLengkap();
        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);

        $this->actingAs($this->timSdm)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertDontSee(self::NO_TUGAS);

        $daftar->update([
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);

        $this->actingAs($this->timSdm)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee(self::NO_TUGAS);
    }

    public function test_tim_sdm_membuka_arsip_daftar_riil(): void
    {
        $this->actingAs($this->timSdm)
            ->get(route('laporan.daftar-riil'))
            ->assertOk()
            ->assertSee('List Daftar Riil');
    }

    /**
     * Arsipnya terbuka bagi Tim SDM, tetapi rekap anggaran tetap tertutup.
     */
    public function test_arsip_tidak_membuka_rekap_anggaran(): void
    {
        $this->actingAs($this->timSdm)
            ->get(route('laporan'))
            ->assertForbidden();
    }

    // ── Rincian Saya ──

    /**
     * Daftar nominatif tidak lagi bermenu pada Rincian Saya: dokumen itu
     * urusan PPK dan tim keuangan, bukan pelaksana perjalanan.
     */
    public function test_pelaksana_tidak_punya_menu_nominatif(): void
    {
        $pelaksana = User::factory()->create();
        $this->usulanLengkap($pelaksana);

        $this->assertFalse(Route::has('rincian-saya.nominatif'));

        $this->actingAs($pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertDontSee('List Daftar Nominatif');
    }

    public function test_pelaksana_melihat_rincian_biayanya(): void
    {
        $pelaksana = User::factory()->create();
        $usulan = $this->usulanLengkap($pelaksana);

        $this->actingAs($pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee($usulan->no_usulan);
    }

    public function test_rincian_biaya_orang_lain_tidak_muncul(): void
    {
        $usulan = $this->usulanLengkap();

        $this->actingAs(User::factory()->create())
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertDontSee($usulan->no_usulan);
    }
}
