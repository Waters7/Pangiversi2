<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaftarRiilTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $this->peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'nama' => 'Rahmat Hidayat',
        ]);
    }

    private function pengguna(PeranPengguna $peran): User
    {
        return User::factory()->create(['role' => $peran->value]);
    }

    /**
     * Lewati tahap kirim-ke-pelaksana dan persetujuannya, karena PPK hanya
     * boleh menandatangani setelah pelaksana menyatakan sikap.
     */
    private function pelaksanaMenyetujui(): void
    {
        $pelaksana = $this->peserta->user ?? User::factory()->create();
        $this->peserta->update(['id_user' => $pelaksana->id]);

        DaftarRiil::firstWhere('id_peserta', $this->peserta->id)?->kirimKePegawai();

        $this->actingAs($pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]));
    }

    // ── Akses halaman ──

    /**
     * Menu Daftar Riil tersendiri sudah dicabut: PPK mencapainya lewat
     * Persetujuan, Tim SDM lewat arsip Laporan.
     */
    public function test_menu_daftar_riil_tersendiri_sudah_dicabut(): void
    {
        $this->assertFalse(app('router')->has('daftar-riil.index'));
    }

    public function test_peran_keuangan_dan_ppk_dapat_membuka_detail_daftar_riil(): void
    {
        foreach ([PeranPengguna::TimKeuangan, PeranPengguna::Bendahara, PeranPengguna::Ppk, PeranPengguna::Pimpinan] as $peran) {
            $this->actingAs($this->pengguna($peran))
                ->get(route('daftar-riil.show', $this->usulan))
                ->assertOk();
        }
    }

    public function test_pengusul_biasa_tidak_dapat_membuka_detail_daftar_riil(): void
    {
        foreach ([PeranPengguna::DosenTendik, PeranPengguna::Outsourcing] as $peran) {
            $this->actingAs($this->pengguna($peran))
                ->get(route('daftar-riil.show', $this->usulan))
                ->assertForbidden();
        }
    }

    public function test_halaman_kelola_menampilkan_satu_baris_per_peserta(): void
    {
        PesertaUsulan::factory()->create(['id_usulan' => $this->usulan->id, 'nama' => 'Sri Handayani']);

        $this->actingAs($this->pengguna(PeranPengguna::Bendahara))
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee('Rahmat Hidayat')
            ->assertSee('Sri Handayani');
    }

    // ── Nominal berasal dari nota pelaksana ──

    /**
     * Daftar riil hanya memuat komponen transport, dan isinya datang dari
     * nota yang diunggah pelaksana — bukan diketik tim keuangan. Sumbernya
     * satu, sehingga tiap angka dapat ditelusuri ke buktinya.
     */
    public function test_nominal_datang_dari_nota_transportasi_pelaksana(): void
    {
        $this->usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 1_250_000]);

        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->assertDatabaseHas('rincian_daftar_riil', [
            'uraian' => 'Rumah ke bandara',
            'nominal' => 1_250_000,
        ]);

        $this->assertSame(1_250_000.0, DaftarRiil::firstWhere('id_peserta', $this->peserta->id)->total_riil);
    }

    public function test_daftar_riil_tidak_lagi_menerima_baris_ketikan(): void
    {
        // Rute penambahan baris manual sengaja dicabut.
        $this->assertFalse(app('router')->has('daftar-riil.rincian.tambah'));
    }

    // ── Tanda tangan PPK ──

    public function test_ppk_dapat_menandatangani_daftar_riil(): void
    {
        $ppk = $this->pengguna(PeranPengguna::Ppk);

        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 900_000,
        ]);

        $this->pelaksanaMenyetujui();

        $this->actingAs($ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $daftar = DaftarRiil::firstWhere('id_peserta', $this->peserta->id);

        $this->assertNotNull($daftar->ditandatangani_at);
        $this->assertSame($ppk->id, $daftar->id_ppk);
    }

    public function test_daftar_dengan_nominal_kosong_tidak_dapat_ditandatangani(): void
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 0,
        ]);

        $this->actingAs($this->pengguna(PeranPengguna::Ppk))
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertStatus(422);
    }

    public function test_bendahara_tidak_dapat_menandatangani(): void
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 900_000,
        ]);

        $this->actingAs($this->pengguna(PeranPengguna::Bendahara))
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    public function test_nominal_terkunci_setelah_ditandatangani(): void
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 900_000,
            'ditandatangani_at' => now(),
        ]);

        // Nota yang disunting setelah daftar ditandatangani tidak ikut
        // mengubah nominalnya.
        $this->usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 100_000]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->assertSame(900_000.0, DaftarRiil::firstWhere('id_peserta', $this->peserta->id)->total_riil);
    }

    public function test_ppk_dapat_membatalkan_tanda_tangan_agar_nominal_dapat_dikoreksi(): void
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 900_000,
            'ditandatangani_at' => now(),
        ]);

        $this->actingAs($this->pengguna(PeranPengguna::Ppk))
            ->delete(route('daftar-riil.batal-tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNull(DaftarRiil::firstWhere('id_peserta', $this->peserta->id)->ditandatangani_at);
    }

    public function test_tanda_tangan_tercatat_di_jejak_audit(): void
    {
        $ppk = $this->pengguna(PeranPengguna::Ppk);

        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 900_000,
        ]);

        $this->pelaksanaMenyetujui();

        $this->actingAs($ppk)->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        $this->assertDatabaseHas('audit_logs', [
            'id_usulan' => $this->usulan->id,
            'id_user' => $ppk->id,
            'deskripsi' => "Daftar Pengeluaran Riil {$this->peserta->nama} pada usulan {$this->usulan->no_usulan} ditandatangani {$ppk->nama}.",
        ]);
    }

    // ── Cetak ──

    public function test_daftar_riil_dapat_dicetak_sebagai_pdf(): void
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 900_000,
        ]);

        $this->actingAs($this->pengguna(PeranPengguna::Ppk))
            ->get(route('daftar-riil.cetak', [$this->usulan, $this->peserta]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_peserta_dari_usulan_lain_tidak_dapat_diakses(): void
    {
        $usulanLain = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $pesertaLain = PesertaUsulan::factory()->create(['id_usulan' => $usulanLain->id]);

        $this->actingAs($this->pengguna(PeranPengguna::Ppk))
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $pesertaLain]))
            ->assertNotFound();
    }
}
