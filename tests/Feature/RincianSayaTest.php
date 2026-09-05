<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Menu "Rincian Saya" milik pelaksana perjalanan.
 *
 * Rincian biaya perjalanan dinas dan daftar pengeluaran riil adalah dua
 * dokumen berbeda: masing-masing bermenu sendiri, memuat komponennya
 * sendiri, dan disikapi sendiri — pelaksana boleh menandatangani yang satu
 * sambil menyanggah yang lain.
 */
class RincianSayaTest extends TestCase
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
        User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'no_tugas' => 'KP.03.01/F.XXXVIII/91/2026',
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-04-06',
            'tanggal_selesai' => '2026-04-08',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);
    }

    /** Berkas sampai di meja pelaksana: terisi, tervalidasi, lalu dikirim. */
    private function berkasSampaiKePelaksana(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));
    }

    private function daftar(): DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $this->usulan->id)->fresh();
    }

    private function setujui(string $jenis): TestResponse
    {
        return $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta, $jenis]));
    }

    /** Jumlah berkas yang benar-benar tampil pada halaman. */
    private function jumlahTampil(TestResponse $halaman): int
    {
        return $halaman->viewData('daftar')->flatten(1)->count();
    }

    private function sanggah(string $jenis, string $alasan): TestResponse
    {
        return $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta, $jenis]), [
                'sanggahan' => $alasan,
            ]);
    }

    // ── Isi tiap submenu ──

    public function test_daftar_riil_hanya_memuat_transport_lokal(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee('Daftar Pengeluaran Riil')
            ->assertSee('Uraian Transportasi')
            // Komponen rincian biaya tidak ikut: dokumennya berbeda.
            ->assertDontSee('Perincian Biaya')
            ->assertDontSee('Uang Harian');
    }

    public function test_rincian_biaya_memuat_semua_komponen_kecuali_transport_lokal(): void
    {
        $this->berkasSampaiKePelaksana();

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Rincian Biaya Perjalanan Dinas')
            ->assertSee('Perincian Biaya')
            ->assertDontSee('Uraian Transportasi');

        // Transport lokal sudah pindah ke daftar riil, jadi tidak dihitung.
        $halaman->assertViewHas('daftar', function ($daftar) {
            $entri = $daftar->flatten(1)->first();

            return $entri['rincian']->every(
                fn ($baris) => $baris->kategori !== KategoriBiaya::TransportLokal
            );
        });
    }

    public function test_nominal_tiap_dokumen_dihitung_terpisah(): void
    {
        $this->berkasSampaiKePelaksana();

        $daftar = $this->daftar();

        $this->assertGreaterThan(0, $daftar->total_riil);
        $this->assertGreaterThan(0, $daftar->totalRincianBiaya());
        $this->assertNotSame($daftar->total_riil, $daftar->totalRincianBiaya());
    }

    // ── Sikap terpisah atas tiap dokumen ──

    public function test_menyetujui_satu_dokumen_tidak_menutup_yang_lain(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->setujui('riil')->assertSessionHas('success');

        $daftar = $this->daftar();

        $this->assertTrue($daftar->jalur()->sudahDisetujui());
        $this->assertFalse($daftar->jalurRincian()->sudahDisetujui());
        $this->assertTrue($daftar->jalurRincian()->masaSanggahBerjalan());
    }

    public function test_menyanggah_rincian_biaya_tanpa_mengganggu_daftar_riil(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->setujui('riil');
        $this->sanggah('rincian', 'Uang harian dihitung 4 hari, SPD menyebut 3 hari.')
            ->assertSessionHas('success');

        $daftar = $this->daftar();

        $this->assertTrue($daftar->jalurRincian()->sedangDisanggah());
        $this->assertTrue($daftar->jalur()->sudahDisetujui());
        $this->assertFalse($daftar->jalur()->sedangDisanggah());
    }

    public function test_sanggahan_memberitahu_tim_keuangan(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->sanggah('rincian', 'Biaya hotel melebihi tarif yang berlaku.');

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->timKeuangan->id,
            'judul' => 'Rincian biaya disanggah',
        ]);
    }

    public function test_dokumen_yang_sudah_disetujui_tidak_dapat_disetujui_ulang(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->setujui('rincian')->assertSessionHas('success');
        $this->setujui('rincian')->assertForbidden();
    }

    public function test_hanya_pelaksananya_yang_boleh_menyikapi(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->actingAs(User::factory()->create())
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta, 'rincian']))
            ->assertForbidden();
    }

    // ── Pengelompokan ──

    public function test_daftar_dikelompokkan_per_bulan_keberangkatan(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee('April 2026');
    }

    public function test_tab_status_memisahkan_yang_perlu_ditanggapi(): void
    {
        $this->berkasSampaiKePelaksana();
        $this->setujui('riil');

        // Daftar riil sudah disetujui, jadi ia pindah dari "Perlu Tanggapan".
        $this->assertSame(1, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil', ['kelompok' => 'menunggu-ppk']))->assertOk()));

        $this->assertSame(0, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil', ['kelompok' => 'perlu-tanggapan']))->assertOk()));

        // Rincian biaya belum disikapi, jadi ia masih menunggu tanggapan.
        $this->assertSame(1, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya', ['kelompok' => 'perlu-tanggapan']))->assertOk()));
    }

    public function test_saringan_bulan_membatasi_daftarnya(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->assertSame(1, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil', ['bulan' => 4]))->assertOk()));

        $this->assertSame(0, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil', ['bulan' => 9]))->assertOk()));
    }

    public function test_saringan_tahun_membatasi_daftarnya(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->assertSame(0, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil', ['tahun' => 2025]))->assertOk()));

        $this->assertSame(1, $this->jumlahTampil($this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil', ['tahun' => 2026]))->assertOk()));
    }

    public function test_berkas_orang_lain_tidak_muncul(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->assertSame(0, $this->jumlahTampil($this->actingAs(User::factory()->create())
            ->get(route('rincian-saya.daftar-riil'))->assertOk()));
    }
}
