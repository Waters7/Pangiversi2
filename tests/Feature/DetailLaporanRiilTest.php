<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Halaman detail laporan tidak lagi menawarkan unduhan CSV, melainkan
 * menampilkan status daftar pengeluaran riil tiap peserta: sudah
 * ditandatangani PPK atau masih tertahan.
 */
class DetailLaporanRiilTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Selesai->value]);
        $this->peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_user' => User::factory()->create()->id,
            'nama' => 'Rahmatullah Pontoh',
        ]);
    }

    private function buka(): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan.show', $this->usulan));
    }

    private function daftar(array $atribut = []): DaftarRiil
    {
        return DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 1_450_000,
            ...$atribut,
        ]);
    }

    // ── Tombol CSV sudah tidak ada ──

    public function test_unduhan_csv_per_perjadin_dihapus(): void
    {
        $this->buka()
            ->assertOk()
            ->assertDontSee('Unduh CSV')
            ->assertDontSee('Unduh Rincian (CSV)');
    }

    public function test_rute_ekspor_detail_tidak_lagi_terdaftar(): void
    {
        $this->assertFalse(
            app('router')->has('laporan.export-detail'),
            'Rute ekspor CSV per perjadin seharusnya sudah dihapus.'
        );
    }

    public function test_daftar_laporan_terbuka_dan_menawarkan_ekspor_excel(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('Ekspor Daftar Nominatif')
            ->assertSee(route('laporan.export-excel'), false)
            ->assertDontSee('Unduh CSV');
    }

    /**
     * Rekap adalah bacaan, bukan meja kerja: kolom aksinya dicabut supaya
     * tabelnya lega dan pembacaannya tidak terganggu tombol.
     */
    public function test_rekap_tidak_lagi_memuat_kolom_aksi(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertDontSee(route('daftar-riil.show', $this->usulan->no_usulan), false)
            ->assertDontSee(route('laporan.show', $this->usulan->no_usulan), false);
    }

    // ── Status daftar riil ──

    public function test_peserta_tanpa_daftar_riil_ditandai_belum_disusun(): void
    {
        $this->buka()
            ->assertOk()
            ->assertSee('Daftar Pengeluaran Riil')
            ->assertSee('Rahmatullah Pontoh')
            ->assertSee('Belum Disusun')
            ->assertSee('Daftar riil belum disusun tim keuangan.');
    }

    public function test_daftar_yang_menunggu_tanggapan_pelaksana_ditandai(): void
    {
        $this->daftar()->kirimKePegawai();

        $this->buka()
            ->assertOk()
            ->assertSee('Menunggu Tanggapan Pelaksana')
            ->assertSee('masa sanggah');
    }

    public function test_daftar_yang_disanggah_ditandai_merah(): void
    {
        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->sanggah('Uang penginapan malam kedua belum dihitung.');

        $this->buka()
            ->assertOk()
            ->assertSee('Disanggah Pelaksana')
            ->assertSee('nominal sedang diperbaiki tim keuangan');
    }

    public function test_daftar_yang_disetujui_pelaksana_menunggu_ppk(): void
    {
        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->setujuiPegawai();

        $this->buka()
            ->assertOk()
            ->assertSee('Disetujui Pelaksana')
            ->assertSee('menunggu tanda tangan PPK');
    }

    public function test_daftar_yang_ditandatangani_menampilkan_ppk_dan_kodenya(): void
    {
        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value, 'nama' => 'Sandra Tombokan']);

        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->setujuiPegawai();
        $daftar->tandaTangani($ppk);

        $this->buka()
            ->assertOk()
            ->assertSee('Ditandatangani PPK')
            ->assertSee('Sandra Tombokan')
            ->assertSee($daftar->fresh()->kode_verifikasi)
            ->assertSee('1.450.000');
    }

    public function test_ringkasan_di_kepala_halaman_menghitung_yang_sudah_ditandatangani(): void
    {
        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);

        PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'nama' => 'Sri Handayani',
        ]);

        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->setujuiPegawai();
        $daftar->tandaTangani($ppk);

        $this->buka()
            ->assertOk()
            ->assertSee('Daftar riil 1/2 ditandatangani PPK');
    }

    public function test_seluruh_peserta_selesai_ditandai_hijau(): void
    {
        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);

        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->setujuiPegawai();
        $daftar->tandaTangani($ppk);

        $this->buka()
            ->assertOk()
            ->assertSee('Daftar riil 1/1 ditandatangani PPK');
    }

    public function test_pengusul_biasa_tidak_dapat_membuka_detail_laporan(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::DosenTendik->value]))
            ->get(route('laporan.show', $this->usulan))
            ->assertForbidden();
    }
}
