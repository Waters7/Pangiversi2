<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Daftar nominatif terbit per surat tugas, bukan per usulan. Ia terbit
 * begitu satu pelaksana menyelesaikan dokumennya dan hanya memuat yang
 * sudah tuntas; kawan seperjalanan yang menyusul bertambah ke daftar yang
 * sama, bukan menahan daftarnya.
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

    public function test_terbit_ketika_pelaksana_menyelesaikan_dokumen(): void
    {
        $this->usulanLengkap();

        $this->assertTrue($this->penyusun()->siapTerbit(self::NO_TUGAS));
    }

    /**
     * Pelaksana lain yang belum lengkap — bahkan yang usulannya masih draf —
     * tidak menahan daftar: yang sudah tuntas tidak perlu menunggu kawan
     * seperjalanannya untuk dibayar.
     */
    public function test_tetap_terbit_meski_pelaksana_lain_belum_lengkap(): void
    {
        $this->usulanLengkap();

        Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Draft->value,
        ]);

        $this->assertTrue($this->penyusun()->siapTerbit(self::NO_TUGAS));
        $this->assertContains(self::NO_TUGAS, $this->penyusun()->suratTugasSiap()->all());
    }

    public function test_belum_terbit_bila_belum_ada_yang_lengkap(): void
    {
        Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->assertFalse($this->penyusun()->siapTerbit(self::NO_TUGAS));
        $this->assertNotContains(self::NO_TUGAS, $this->penyusun()->suratTugasSiap()->all());
    }

    /**
     * Yang belum tuntas belum tercantum — angkanya belum disahkan PPK —
     * tapi namanya disebut supaya PPK tahu daftarnya masih akan bertambah.
     */
    public function test_pelaksana_yang_belum_lengkap_tidak_tercantum(): void
    {
        $this->usulanLengkap();

        $menyusul = User::factory()->create(['nama' => 'Pingkan Umboh']);
        Usulan::factory()->create([
            'id_user' => $menyusul->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Draft->value,
        ]);

        $baris = $this->penyusun()->baris(self::NO_TUGAS);

        $this->assertCount(1, $baris);
        $this->assertNotContains('Pingkan Umboh', $baris->pluck('nama')->all());
        $this->assertSame(['Pingkan Umboh'], $this->penyusun()->menunggu(self::NO_TUGAS)->all());
    }

    public function test_usulan_ditolak_tidak_dihitung_menunggu(): void
    {
        $this->usulanLengkap();

        Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Ditolak->value,
        ]);

        $this->assertTrue($this->penyusun()->menunggu(self::NO_TUGAS)->isEmpty());
    }

    /**
     * Kawan seperjalanan yang menyusul bertambah ke daftar yang sama —
     * tidak lahir daftar kedua untuk surat tugas yang sama.
     */
    public function test_pelaksana_yang_menyusul_bertambah_ke_daftar_yang_sama(): void
    {
        $this->usulanLengkap();
        $this->penyusun()->terbitkanYangSiap();

        $menyusul = User::factory()->create(['nama' => 'Pingkan Umboh']);
        $usulanMenyusul = Usulan::factory()->create([
            'id_user' => $menyusul->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->assertCount(1, $this->penyusun()->baris(self::NO_TUGAS));

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulanMenyusul->id]);
        $usulanMenyusul = $this->lengkapiPertanggungjawaban($usulanMenyusul);
        app(SinkronBiayaDokumen::class)->selaraskan($usulanMenyusul);
        $this->tandatanganiBerkas($usulanMenyusul->fresh(), $this->ppk);

        $this->penyusun()->terbitkanYangSiap();

        $baris = $this->penyusun()->baris(self::NO_TUGAS);

        $this->assertSame(1, DaftarNominatif::where('no_tugas', self::NO_TUGAS)->count());
        $this->assertCount(2, $baris);
        $this->assertSame([1, 2], $baris->pluck('nomor')->all());
        $this->assertContains('Pingkan Umboh', $baris->pluck('nama')->all());
        $this->assertTrue($this->penyusun()->menunggu(self::NO_TUGAS)->isEmpty());
    }

    /**
     * Pelunasan tidak menunggu daftar nominatif — kawan seperjalanan yang
     * belum tercantum tetap boleh dilunasi selama laporannya sudah
     * dikonfirmasi pimpinan.
     */
    public function test_pelunasan_tidak_menunggu_pencantuman_pada_nominatif(): void
    {
        Storage::fake('public');

        $this->usulanLengkap();
        $this->penyusun()->terbitkan(self::NO_TUGAS);

        $menyusul = Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $menyusul->id]);
        $this->konfirmasiLaporan($menyusul);

        $bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);

        $this->actingAs($bendahara)->post(route('keuangan.bayar-uang-muka', $menyusul), [
            'tanggal_transfer' => today()->subDays(3)->toDateString(),
            'bukti_transfer' => UploadedFile::fake()->create('um.pdf', 40, 'application/pdf'),
        ]);

        $this->actingAs($bendahara)
            ->post(route('keuangan.bayar-sisa', $menyusul), [
                'tanggal_pelunasan' => today()->toDateString(),
                'bukti_pelunasan' => UploadedFile::fake()->create('lunas.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionMissing('error');

        $this->assertSame(Keuangan::STATUS_LUNAS, $menyusul->fresh('keuangan')->keuangan->status);
    }

    public function test_ppk_diberi_tahu_pelaksana_yang_belum_tercantum(): void
    {
        $this->usulanLengkap();

        Usulan::factory()->create([
            'id_user' => User::factory()->create(['nama' => 'Pingkan Umboh'])->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Draft->value,
        ]);

        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif'))
            ->assertOk()
            ->assertSee('1 pelaksana lain pada surat tugas ini belum tercantum')
            ->assertSee('Pingkan Umboh');

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif.detail', $daftar))
            ->assertOk()
            ->assertSee('Pingkan Umboh');
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

    public function test_verifikasi_nominatif_dikelompokkan_per_bulan_surat_tugas(): void
    {
        $this->usulanLengkap();
        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);
        $daftar->update(['tanggal_tugas' => '2026-07-14']);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif'))
            ->assertOk()
            ->assertSee('Juli 2026')
            ->assertViewHas('belum', fn ($belum) => $belum->keys()->first() === 'Juli 2026')
            ->assertViewHas('tahunTersedia', fn ($tahun) => $tahun->contains(2026));

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif', ['tahun' => 2026, 'bulan' => 8]))
            ->assertOk()
            // Daftar lompat tetap memuat seluruh surat tugas; yang disaring isinya.
            ->assertViewHas('belum', fn ($belum) => $belum->isEmpty())
            ->assertViewHas('sudah', fn ($sudah) => $sudah->isEmpty());
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

    // ── List Daftar Nominatif (tim keuangan & Tim SDM) ──

    /**
     * Seluruh daftar yang terbit tampil — bukan hanya yang sudah diterima —
     * lengkap dengan statusnya, supaya yang masih menunggu PPK pun terlihat.
     */
    public function test_list_memuat_seluruh_daftar_beserta_statusnya(): void
    {
        $this->usulanLengkap();
        $daftar = $this->penyusun()->terbitkan(self::NO_TUGAS);

        $this->actingAs($this->timSdm)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee(self::NO_TUGAS)
            ->assertSee('Menunggu Tanda Tangan PPK')
            ->assertSee('Belum ditandatangani PPK')
            ->assertSee('belum dikirim ke tim keuangan');

        $daftar->update([
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee(self::NO_TUGAS)
            ->assertSee('Terkirim ke Tim Keuangan')
            ->assertSee('Ditandatangani PPK '.$this->ppk->nama)
            ->assertSee('diterima tim keuangan');
    }

    public function test_list_dapat_disaring_menurut_status(): void
    {
        $this->usulanLengkap();
        $this->penyusun()->terbitkan(self::NO_TUGAS);

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif', ['status' => 'diterima']))
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar) => $daftar->isEmpty());

        $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif', ['status' => 'menunggu']))
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar) => $daftar->flatten(1)->count() === 1)
            ->assertViewHas('jumlahStatus', fn ($jumlah) => $jumlah['menunggu'] === 1 && $jumlah['diterima'] === 0);
    }

    /**
     * Tiap pelaksana di bawah surat tugas disebut beserta sejauh mana tanda
     * tangannya: yang sudah disahkan PPK, yang baru ditandatangani
     * pelaksana, dan yang usulannya belum berjalan.
     */
    public function test_list_menyebut_siapa_saja_yang_sudah_menandatangani(): void
    {
        $tuntas = User::factory()->create(['nama' => 'Junita Ratela']);
        $this->usulanLengkap($tuntas);

        $setengah = User::factory()->create(['nama' => 'Meike Kaunang']);
        $usulanSetengah = Usulan::factory()->create([
            'id_user' => $setengah->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);
        $peserta = $usulanSetengah->peserta()->create(['id_user' => $setengah->id, 'nama' => $setengah->nama, 'peran' => 'ketua']);
        DaftarRiil::create([
            'id_usulan' => $usulanSetengah->id,
            'id_peserta' => $peserta->id,
            'total_riil' => 100_000,
            'dikirim_ke_pegawai_at' => now()->subDay(),
            'batas_sanggah' => today()->addDays(6),
            'disetujui_pegawai_at' => now(),
            'rincian_disetujui_at' => now(),
        ]);

        Usulan::factory()->create([
            'id_user' => User::factory()->create(['nama' => 'Pingkan Umboh'])->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Draft->value,
        ]);

        $this->penyusun()->terbitkan(self::NO_TUGAS);

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee('Tanda tangan pelaksana')
            ->assertSee('1 dari 3 sudah disahkan PPK')
            ->assertSee('Junita Ratela')
            ->assertSee('Ditandatangani PPK '.$this->ppk->nama)
            ->assertSee('Meike Kaunang')
            ->assertSee('Ditandatangani pelaksana, menunggu PPK')
            ->assertSee('Pingkan Umboh')
            ->assertSee('Usulan masih draf, belum diajukan');

        $tandaTangan = $halaman->viewData('daftar')->flatten(1)->first()['tandaTangan'];

        $this->assertSame(
            ['ppk', 'pelaksana', 'draf'],
            $tandaTangan->pluck('tahap')->all(),
        );
        $this->assertSame([true, false, false], $tandaTangan->pluck('tercantum')->all());
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
