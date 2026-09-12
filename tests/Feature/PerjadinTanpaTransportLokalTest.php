<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PelacakUsulan;
use App\Services\PenagihDokumen;
use App\Services\PengirimanBerkas;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perjalanan tanpa transport lokal — diantar kendaraan dinas, dijemput
 * panitia — punya daftar pengeluaran riil bernilai nol. Daftar itu tidak
 * perlu diperiksa, disetujui, maupun ditandatangani siapa pun; yang
 * menentukan adalah rincian biayanya. Dulu tiap tahap menuntut nominal
 * riil di atas nol dan nota sedikitnya satu ruas, sehingga berkas seperti
 * ini tidak pernah lengkap, tidak pernah tercantum di daftar nominatif,
 * dan tidak pernah selesai.
 */
class PerjadinTanpaTransportLokalTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $ppk;

    private User $timKeuangan;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->ppk = User::factory()->ppk()->create();
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.03.01/F.XXXVIII/90/2026',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'peran' => 'ketua',
        ]);

        // Berkas lengkap, tanpa satu pun nota transport lokal.
        $this->lengkapiPertanggungjawaban($this->usulan);
        $this->usulan->notaTransport()->delete();
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()
            ->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);
    }

    private function riil(): DaftarRiil
    {
        return DaftarRiil::where('id_usulan', $this->usulan->id)->firstOrFail()->fresh();
    }

    /** Pelaksana menyetujui lalu PPK menandatangani — rincian biaya saja. */
    private function sahkanRincian(): void
    {
        $this->riil()->kirimKePegawai();
        $this->riil()->jalurRincian()->setujui();
        $this->riil()->jalurRincian()->tandaTangani($this->ppk);
    }

    public function test_berkas_lengkap_tanpa_nota_transport_lokal(): void
    {
        $riil = $this->riil();

        $this->assertSame(0.0, (float) $riil->total_riil);
        $this->assertFalse($riil->berlaku());
        $this->assertTrue(app(PenagihDokumen::class)->lengkap($this->usulan->fresh()));
    }

    /**
     * Transport lokal nol tidak tampil di menu pemeriksaannya, jadi tidak
     * boleh pula ditunggu validasinya: berkas terkirim begitu seluruh
     * rincian biaya divalidasi.
     */
    public function test_berkas_terkirim_otomatis_tanpa_validasi_transport_lokal(): void
    {
        $this->assertNull(app(PengirimanBerkas::class)->alasanBelumSiap($this->usulan->fresh(), $this->riil()));
        $this->assertTrue(app(PengirimanBerkas::class)->kirimBilaSiap($this->usulan->fresh()));
        $this->assertTrue($this->riil()->sudahDikirimKePegawai());
    }

    public function test_tanda_tangan_rincian_saja_sudah_mengesahkan_berkas(): void
    {
        $this->riil()->kirimKePegawai();
        $this->riil()->jalurRincian()->setujui();

        $this->assertTrue($this->riil()->disetujuiPelaksanaSeluruhnya());

        $this->riil()->jalurRincian()->tandaTangani($this->ppk);

        $riil = $this->riil();

        $this->assertTrue($riil->disahkanPpkSeluruhnya());
        $this->assertNull($riil->ditandatangani_at, 'Daftar riil nol tidak perlu ditandatangani.');
        $this->assertNotNull($riil->waktuDisahkanPpk());
    }

    public function test_nominatif_terbit_dan_pelaksana_tercantum(): void
    {
        $this->sahkanRincian();

        $penyusun = app(PenyusunNominatif::class);

        $this->assertTrue($penyusun->disahkanPpk($this->usulan->fresh()));
        $this->assertTrue($penyusun->siapTerbit($this->usulan->no_tugas));
        $this->assertContains($this->usulan->no_tugas, $penyusun->suratTugasSiap()->all());

        $baris = $penyusun->baris($this->usulan->no_tugas);

        $this->assertCount(1, $baris);
        $this->assertSame(0.0, $baris->first()['transport']);
    }

    public function test_arsip_rincian_lengkap_memuatnya(): void
    {
        $this->sahkanRincian();

        $halaman = $this->actingAs($this->timKeuangan)
            ->get(route('laporan.rincian-lengkap'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan);

        $this->assertSame(1, $halaman->viewData('jumlahBerkas'));
    }

    public function test_pelacakan_menandai_tanda_tangan_pelaksana_dan_ppk(): void
    {
        $this->sahkanRincian();

        $tonggak = app(PelacakUsulan::class)->tonggak($this->usulan->fresh());

        $this->assertTrue($tonggak->firstWhere('judul', 'Ditandatangani pelaksana')['selesai']);
        $this->assertTrue($tonggak->firstWhere('judul', 'Ditandatangani PPK')['selesai']);
    }

    public function test_usulan_dapat_selesai(): void
    {
        $this->sahkanRincian();
        $this->usulan->keuangan->update(['status' => 'lunas']);

        $this->assertTrue($this->usulan->fresh()->checkCompletion());
        $this->assertSame(StatusUsulan::Selesai->value, $this->usulan->fresh()->status);
    }

    /**
     * Yang benar-benar punya transport lokal tetap menuntut daftar riilnya
     * disahkan — aturan lama tidak berubah bagi mereka.
     */
    public function test_yang_bertransport_lokal_tetap_menunggu_daftar_riilnya(): void
    {
        $this->usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 120_000, 'bukti' => 'dokumen/nota.pdf']);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $riil = $this->riil();
        $this->assertTrue($riil->berlaku());
        $this->assertStringContainsString(
            'belum divalidasi',
            app(PengirimanBerkas::class)->alasanBelumSiap($this->usulan->fresh(), $riil),
        );

        $riil->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);
        $this->sahkanRincian();

        $this->assertFalse($this->riil()->disahkanPpkSeluruhnya());
        $this->assertFalse(app(PenyusunNominatif::class)->siapTerbit($this->usulan->no_tugas));

        $this->riil()->jalur()->setujui();
        $this->riil()->jalur()->tandaTangani($this->ppk);

        $this->assertTrue($this->riil()->disahkanPpkSeluruhnya());
        $this->assertTrue(app(PenyusunNominatif::class)->siapTerbit($this->usulan->no_tugas));
    }
}
