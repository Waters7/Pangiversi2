<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yang dipotong 80/20 hanya uang harian.
 *
 * Tiket pesawat dan biaya hotel sudah dibayarkan instansi, jadi masuk uang
 * muka seutuhnya. Sisa bayar tinggal 20% uang harian, dilunasi bersama
 * penggantian transport lokal setelah daftar nominatif terbit.
 */
class PembagianUangMukaTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private Keuangan $keuangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        $this->keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
    }

    private function tambah(KategoriBiaya $kategori, string $komponen, float $jumlah): void
    {
        $this->keuangan->rincianBiaya()->create([
            'kategori' => $kategori->value,
            'komponen' => $komponen,
            'volume' => 1,
            'satuan' => 'paket',
            'harga_satuan' => $jumlah,
            'jumlah' => $jumlah,
        ]);

        $this->keuangan->hitungTotal();
        $this->keuangan->refresh();
    }

    // ── Rumus ──

    public function test_uang_harian_dipotong_delapan_puluh_persen(): void
    {
        $this->tambah(KategoriBiaya::UangHarian, 'Uang harian', 1_590_000);

        $this->assertSame(1_590_000.0, $this->keuangan->total);
        $this->assertSame(1_272_000.0, $this->keuangan->uang_muka);
        $this->assertSame(318_000.0, $this->keuangan->sisa);
    }

    /**
     * Tiket dan hotel sudah dibayarkan instansi, jadi tidak menyisakan
     * apa pun untuk dilunasi.
     */
    public function test_tiket_dan_hotel_masuk_uang_muka_seutuhnya(): void
    {
        $this->tambah(KategoriBiaya::Transport, 'Tiket Pergi', 2_450_000);
        $this->tambah(KategoriBiaya::Penginapan, 'Biaya Hotel', 1_400_000);

        $this->assertSame(3_850_000.0, $this->keuangan->uang_muka);
        $this->assertSame(0.0, $this->keuangan->sisa);
    }

    public function test_campuran_dihitung_per_komponen(): void
    {
        $this->tambah(KategoriBiaya::Transport, 'Tiket Pergi', 2_450_000);
        $this->tambah(KategoriBiaya::Transport, 'Tiket Pulang', 2_285_000);
        $this->tambah(KategoriBiaya::UangHarian, 'Uang harian', 1_590_000);
        $this->tambah(KategoriBiaya::Penginapan, 'Biaya Hotel', 1_400_000);

        // Sisa = 20% × 1.590.000; selebihnya masuk uang muka.
        $this->assertSame(7_725_000.0, $this->keuangan->total);
        $this->assertSame(318_000.0, $this->keuangan->sisa);
        $this->assertSame(7_407_000.0, $this->keuangan->uang_muka);
    }

    /**
     * Uang muka dan sisa selalu berjumlah tepat sama dengan totalnya —
     * tidak ada rupiah yang hilang atau terhitung dua kali.
     */
    public function test_uang_muka_dan_sisa_selalu_menjumlah_total(): void
    {
        $this->tambah(KategoriBiaya::Transport, 'Tiket Pergi', 2_450_000);
        $this->tambah(KategoriBiaya::UangHarian, 'Uang harian', 1_337_777);

        $this->assertSame(
            $this->keuangan->total,
            $this->keuangan->uang_muka + $this->keuangan->sisa,
        );
    }

    public function test_tanpa_uang_harian_tidak_ada_sisa(): void
    {
        $this->tambah(KategoriBiaya::Transport, 'Tiket Pergi', 2_450_000);

        $this->assertSame(0.0, $this->keuangan->sisa);
        $this->assertSame($this->keuangan->total, $this->keuangan->uang_muka);
    }

    // ── Pelunasan ──

    public function test_pelunasan_menambahkan_penggantian_transport(): void
    {
        $this->tambah(KategoriBiaya::UangHarian, 'Uang harian', 1_590_000);

        $peserta = PesertaUsulan::factory()->create(['id_usulan' => $this->usulan->id]);

        DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now(),
            'id_ppk' => User::factory()->ppk()->create()->id,
        ]);

        $keuangan = $this->keuangan->fresh();
        $keuangan->load('usulan.daftarRiil');

        $this->assertSame(474_500.0, $keuangan->reimbursementTransport());
        $this->assertSame(792_500.0, $keuangan->nilaiPelunasan());
    }

    // ── Transport lokal bermenu sendiri ──

    public function test_transport_lokal_tidak_tampil_pada_tabel_rincian(): void
    {
        $this->tambah(KategoriBiaya::UangHarian, 'Uang harian', 1_590_000);
        $this->tambah(KategoriBiaya::TransportLokal, 'Transport lokal warisan', 300_000);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertDontSee('Transport lokal warisan');
    }

    public function test_menu_transport_lokal_menampilkan_daftar_riil(): void
    {
        $peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'nama' => 'Rahmatullah Pontoh',
        ]);

        $this->usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 75_000]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('keuangan.transport-lokal'))
            ->assertOk()
            ->assertSee('Transport Lokal')
            ->assertSee($peserta->nama)
            ->assertSee('Rumah ke bandara');
    }

    public function test_menu_transport_lokal_tertutup_bagi_pengusul(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::DosenTendik->value]))
            ->get(route('keuangan.transport-lokal'))
            ->assertForbidden();
    }

    // ── Tombol kirim ke pelaksana ──

    public function test_tombol_kirim_tampil_setelah_nominal_divalidasi(): void
    {
        $timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);

        $peserta = $this->usulan->peserta()->create([
            'id_user' => $this->usulan->id_user,
            'nama' => $this->usulan->user?->nama ?? 'Pelaksana',
            'peran' => 'ketua',
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        // Selama masih ada yang belum diperiksa, tombolnya tertahan.
        $this->actingAs($timKeuangan)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('Kirim ke Pelaksana')
            ->assertSee('belum divalidasi');

        $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()
            ->whereNull('divalidasi_at')
            ->update(['divalidasi_at' => now(), 'id_validator' => $timKeuangan->id]);

        $this->actingAs($timKeuangan)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee(route('daftar-riil.kirim-pegawai', [$this->usulan->no_usulan, $peserta]))
            ->assertDontSee('belum divalidasi');
    }
}
