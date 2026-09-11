<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Urutan pertanggungjawaban perjalanan dinas, dari ujung ke ujung.
 *
 * Pelaksana mengisi dokumen → tim keuangan memeriksa dan memisahkan
 * transport lokal ke daftar riil, komponen lain ke rincian biaya → kedua
 * dokumen dikirim kembali ke pelaksana untuk disanggah atau ditandatangani
 * → PPK menandatangani keduanya → keduanya menjadi dasar daftar nominatif
 * → PPK menandatangani nominatif dan mengirimkannya ke tim keuangan →
 * barulah pelunasan keluar: sisa 20% ditambah penggantian transport lokal.
 *
 * Tiap tahap menahan tahap berikutnya. Pengujian ini menjaga urutannya agar
 * tidak ada langkah yang dapat dilompati diam-diam.
 */
class AlurPertanggungjawabanTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/77/2026';

    private User $pelaksana;

    private User $timKeuangan;

    private User $bendahara;

    private User $ppk;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        $this->bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);
        $this->ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'no_tugas' => self::NO_TUGAS,
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

    // ── Langkah-langkah alur ──

    /** Pelaksana mengisi dokumen; nominalnya tersalin dan terpisah sendiri. */
    private function pelaksanaMengisiDokumen(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);

        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
    }

    /** Tim keuangan memeriksa seluruh nominal, termasuk transport lokal. */
    private function timKeuanganMemvalidasi(): void
    {
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);
    }

    private function kirimKePelaksana(): TestResponse
    {
        return $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));
    }

    private function daftar(): DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $this->usulan->id);
    }

    private function lunasi(): TestResponse
    {
        $this->actingAs($this->bendahara)->post(route('keuangan.bayar-uang-muka', $this->usulan), [
            'tanggal_transfer' => today()->subDays(3)->toDateString(),
            'bukti_transfer' => UploadedFile::fake()->create('um.pdf', 40, 'application/pdf'),
        ]);

        return $this->actingAs($this->bendahara)->post(route('keuangan.bayar-sisa', $this->usulan), [
            'tanggal_pelunasan' => today()->toDateString(),
            'bukti_pelunasan' => UploadedFile::fake()->create('lunas.pdf', 40, 'application/pdf'),
        ]);
    }

    // ── Tahap 1: pemisahan dokumen ──

    /**
     * Transport lokal masuk daftar riil, komponen lain masuk rincian biaya.
     * Keduanya tidak pernah memuat komponen yang sama.
     */
    public function test_transport_lokal_dan_komponen_lain_terpisah(): void
    {
        $this->pelaksanaMengisiDokumen();

        $kategori = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya
            ->pluck('kategori')->map->value->unique()->sort()->values()->all();

        $this->assertSame(['penginapan', 'transport'], $kategori);
        $this->assertGreaterThan(0, $this->daftar()->total_riil);
    }

    public function test_berkas_diajukan_ke_tim_keuangan_bukan_ke_ppk(): void
    {
        $this->pelaksanaMengisiDokumen();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->timKeuangan->id,
            'judul' => 'Berkas pertanggungjawaban menunggu verifikasi',
        ]);

        $this->assertDatabaseMissing('notifikasi', [
            'id_user' => $this->ppk->id,
            'judul' => 'Berkas pertanggungjawaban menunggu verifikasi',
        ]);
    }

    // ── Tahap 2: dikirim ke pelaksana ──

    public function test_belum_dapat_dikirim_selama_nominal_belum_diperiksa(): void
    {
        $this->pelaksanaMengisiDokumen();

        $this->kirimKePelaksana()->assertSessionHas('error');

        $this->assertNull($this->daftar()->dikirim_ke_pegawai_at);
    }

    public function test_tim_keuangan_mengirim_kedua_dokumen(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();

        $this->kirimKePelaksana()->assertSessionHas('success');

        $this->assertNotNull($this->daftar()->dikirim_ke_pegawai_at);

        // Pemberitahuannya menyebut kedua dokumen, bukan hanya daftar riil.
        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Berkas pertanggungjawaban menunggu tanda tangan Anda',
        ]);
    }

    public function test_pelaksana_melihat_kedua_dokumen_pada_menunya_masing_masing(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee('Daftar Pengeluaran Riil')
            ->assertDontSee('Perincian Biaya');

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Rincian Biaya Perjalanan Dinas')
            ->assertDontSee('Uraian Transportasi');
    }

    // ── Tahap 3: sanggahan kembali ke tim keuangan ──

    public function test_sanggahan_dikembalikan_ke_tim_keuangan(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Transport lokal dari bandara belum dihitung sesuai nota.',
            ])
            ->assertSessionHas('success');

        $this->assertTrue($this->daftar()->sedangDisanggah());

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->timKeuangan->id,
            'judul' => 'Rincian biaya disanggah',
        ]);
    }

    // ── Tahap 4: tanda tangan pelaksana lalu PPK ──

    public function test_tanda_tangan_pelaksana_meneruskan_ke_ppk(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNotNull($this->daftar()->disetujui_pegawai_at);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->ppk->id,
            'judul' => 'Berkas pertanggungjawaban menunggu tanda tangan Anda',
        ]);
    }

    // ── Tahap 4b: PPK mengembalikan berkas ke tim keuangan ──

    /** Berkas yang sudah sampai di meja PPK, siap ditandatangani. */
    private function berkasDiMejaPpk(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->kirimKePelaksana();
        $this->pelaksanaMenyetujuiKeduanya();
    }

    /** Pelaksana menyetujui kedua dokumen, satu per satu. */
    private function pelaksanaMenyetujuiKeduanya(): void
    {
        foreach (['riil', 'rincian'] as $jenis) {
            $this->actingAs($this->pelaksana)
                ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta, $jenis]));
        }
    }

    /** PPK menandatangani kedua dokumen, satu per satu. */
    private function ppkMenandatanganiKeduanya(): void
    {
        foreach (['riil', 'rincian'] as $jenis) {
            $this->actingAs($this->ppk)
                ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta, $jenis]));
        }
    }

    private function kembalikan(string $alasan): TestResponse
    {
        return $this->actingAs($this->ppk)
            ->put(route('daftar-riil.kembalikan', [$this->usulan, $this->peserta]), [
                'alasan_kembali' => $alasan,
            ]);
    }

    public function test_ppk_mengembalikan_berkas_dengan_alasan(): void
    {
        $this->berkasDiMejaPpk();

        $this->kembalikan('Nominal uang harian belum sesuai lama perjalanan pada SPD.')
            ->assertSessionHas('success');

        $daftar = $this->daftar();

        $this->assertTrue($daftar->sedangDikembalikan());
        $this->assertSame($this->ppk->id, $daftar->id_pengembali);
        $this->assertFalse($daftar->sudah_ditandatangani);

        // Validasinya ikut dicabut: tim keuangan harus memeriksanya ulang.
        $this->assertFalse($daftar->sudahDivalidasi());
        $this->assertNull($daftar->dikirim_ke_pegawai_at);
    }

    public function test_pengembalian_memberitahu_tim_keuangan(): void
    {
        $this->berkasDiMejaPpk();

        $this->kembalikan('Transport lokal ke bandara belum ada notanya.');

        foreach ([$this->timKeuangan, $this->bendahara] as $penerima) {
            $this->assertDatabaseHas('notifikasi', [
                'id_user' => $penerima->id,
                'judul' => 'Berkas dikembalikan PPK',
            ]);
        }
    }

    public function test_pengembalian_wajib_beralasan(): void
    {
        $this->berkasDiMejaPpk();

        $this->kembalikan('kurang')->assertSessionHasErrors('alasan_kembali');

        $this->assertFalse($this->daftar()->sedangDikembalikan());
    }

    public function test_berkas_yang_sudah_ditandatangani_tidak_dapat_dikembalikan(): void
    {
        $this->berkasDiMejaPpk();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        $this->kembalikan('Ternyata nominalnya keliru.')->assertForbidden();

        $this->assertTrue($this->daftar()->fresh()->sudah_ditandatangani);
    }

    public function test_berkas_yang_dikembalikan_dapat_diajukan_ulang(): void
    {
        $this->berkasDiMejaPpk();
        $this->kembalikan('Biaya hotel melebihi standar, mohon diperiksa ulang.');

        // Tim keuangan memvalidasi ulang lalu mengirimkannya lagi ke pelaksana.
        $this->timKeuanganMemvalidasi();
        $this->kirimKePelaksana()->assertSessionHas('success');

        $this->assertFalse($this->daftar()->sedangDikembalikan());

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]));

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertTrue($this->daftar()->sudah_ditandatangani);
    }

    public function test_hanya_ppk_yang_boleh_mengembalikan(): void
    {
        $this->berkasDiMejaPpk();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.kembalikan', [$this->usulan, $this->peserta]), [
                'alasan_kembali' => 'Saya tidak setuju dengan nominalnya.',
            ])
            ->assertForbidden();
    }

    // ── Konfirmasi sebelum tanda tangan ──

    public function test_halaman_ppk_meminta_konfirmasi_sebelum_menandatangani(): void
    {
        $this->berkasDiMejaPpk();

        foreach (['persetujuan.rincian-biaya', 'persetujuan.daftar-riil'] as $halaman) {
            $this->actingAs($this->ppk)
                ->get(route($halaman))
                ->assertOk()
                ->assertSee('Apakah Anda yakin dokumen ini sudah benar dikerjakan?', escape: false)
                ->assertSee('Ya, Tandatangani')
                ->assertSee('Alasan pengembalian');
        }
    }
    // ── Tahap 5: nominatif ──

    public function test_nominatif_belum_terbit_sebelum_ppk_menandatangani(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();

        $this->assertFalse(app(PenyusunNominatif::class)->siapTerbit(self::NO_TUGAS));
    }

    public function test_nominatif_terbit_setelah_kedua_dokumen_ditandatangani_ppk(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->tandatanganiBerkas($this->usulan->fresh(), $this->ppk);

        $this->assertTrue(app(PenyusunNominatif::class)->siapTerbit(self::NO_TUGAS));
    }

    // ── Tahap 6: pelunasan ──

    /**
     * Sisa boleh dibayarkan sebelum maupun sesudah tanda tangan: pengesahan
     * pelaksana, PPK, dan daftar nominatif tidak menahan pelunasan. Yang
     * ditunggu hanya konfirmasi laporan oleh pimpinan.
     */
    public function test_pelunasan_boleh_keluar_sebelum_ditandatangani(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->konfirmasiLaporan($this->usulan);

        $this->lunasi()->assertSessionMissing('error');

        $this->assertSame(Keuangan::STATUS_LUNAS, $this->usulan->fresh('keuangan')->keuangan->status);
    }

    public function test_pelunasan_boleh_keluar_sebelum_nominatif_dikirim(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->tandatanganiBerkas($this->usulan->fresh(), $this->ppk);
        $this->konfirmasiLaporan($this->usulan);

        // Nominatif terbit tapi belum dikirim PPK ke tim keuangan.
        app(PenyusunNominatif::class)->terbitkan(self::NO_TUGAS);

        $this->lunasi()->assertSessionMissing('error');

        $this->assertSame(Keuangan::STATUS_LUNAS, $this->usulan->fresh('keuangan')->keuangan->status);
    }

    public function test_pelunasan_keluar_setelah_nominatif_diterima_tim_keuangan(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->tandatanganiBerkas($this->usulan->fresh(), $this->ppk);
        $this->terbitkanNominatif($this->usulan, $this->ppk);
        $this->konfirmasiLaporan($this->usulan);

        $this->lunasi();

        $this->assertSame(Keuangan::STATUS_LUNAS, $this->usulan->fresh('keuangan')->keuangan->status);
    }

    /**
     * Yang dibayarkan pada pelunasan bukan hanya sisa 20%, tetapi ditambah
     * penggantian transport lokal — angka itu memang di luar rincian biaya.
     */
    public function test_pelunasan_mencakup_sisa_dan_reimbursement_transport(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->tandatanganiBerkas($this->usulan->fresh(), $this->ppk);

        $keuangan = $this->usulan->fresh('keuangan')->keuangan;
        $riil = $this->daftar()->total_riil;

        $this->assertGreaterThan(0, $riil);
        $this->assertSame($riil, $keuangan->reimbursementTransport());
        $this->assertSame((float) $keuangan->sisa + $riil, $keuangan->nilaiPelunasan());
    }

    /**
     * Daftar riil yang belum ditandatangani belum boleh diganti — nominalnya
     * masih dapat berubah.
     */
    public function test_reimbursement_hanya_dari_daftar_yang_ditandatangani(): void
    {
        $this->pelaksanaMengisiDokumen();

        $keuangan = $this->usulan->fresh('keuangan')->keuangan;

        $this->assertSame(0.0, $keuangan->reimbursementTransport());
    }

    // ── Rincian biaya tidak memuat transport lokal ──

    public function test_rincian_biaya_tidak_pernah_memuat_transport_lokal(): void
    {
        $this->pelaksanaMengisiDokumen();

        $transportLokal = $this->usulan->fresh('keuangan')->keuangan->rincianBiaya
            ->filter(fn (RincianBiaya $baris) => $baris->kategori === KategoriBiaya::TransportLokal);

        $this->assertCount(0, $transportLokal);
    }

    public function test_nominatif_membaca_transport_dari_daftar_riil(): void
    {
        $this->pelaksanaMengisiDokumen();
        $this->timKeuanganMemvalidasi();
        $this->tandatanganiBerkas($this->usulan->fresh(), $this->ppk);

        DaftarNominatif::updateOrCreate(['no_tugas' => self::NO_TUGAS], []);

        $baris = app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->first();

        $this->assertSame($this->daftar()->total_riil, $baris['transport']);
    }
}
