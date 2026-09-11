<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Rincian Biaya (Lampiran II) dan Daftar Pengeluaran Riil (Lampiran IX)
 * adalah dua dokumen berbeda, jadi bermenu sendiri-sendiri.
 *
 * Rincian memuat seluruh komponen kecuali transport lokal; daftar riil
 * hanya memuat transport lokal. Keduanya tidak pernah dibandingkan satu
 * sama lain karena memang bukan angka yang sebanding.
 */
class RincianBiayaPpkTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private Keuangan $keuangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);

        $this->peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'nama' => 'Rahmatullah Pontoh',
        ]);

        $this->keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $komponen = [
            [KategoriBiaya::Penginapan, 'Uang penginapan', 2, 730_000],
            [KategoriBiaya::Transport, 'Tiket pesawat PP', 1, 4_749_300],
            [KategoriBiaya::UangHarian, 'Uang harian', 3, 530_000],
        ];

        foreach ($komponen as [$kategori, $nama, $volume, $harga]) {
            RincianBiaya::factory()->create([
                'id_keuangan' => $this->keuangan->id,
                'kategori' => $kategori->value,
                'komponen' => $nama,
                'volume' => $volume,
                'satuan' => 'OH',
                'harga_satuan' => $harga,
                'jumlah' => $volume * $harga,
            ]);
        }

        $this->keuangan->hitungTotal();
    }

    private function bukaRincian(): TestResponse
    {
        return $this->actingAs(User::factory()->ppk()->create())
            ->get(route('persetujuan.rincian-biaya'))
            ->assertOk();
    }

    private function tambahTransportLokal(): RincianBiaya
    {
        return RincianBiaya::factory()->create([
            'id_keuangan' => $this->keuangan->id,
            'kategori' => KategoriBiaya::TransportLokal->value,
            'komponen' => 'Transport lokal bandara',
            'volume' => 2,
            'satuan' => 'kali',
            'harga_satuan' => 150_000,
            'jumlah' => 300_000,
        ]);
    }

    // ── Pengelompokan per bulan dan tahun ──

    public function test_daftar_dikelompokkan_per_bulan_keberangkatan(): void
    {
        $this->usulan->update(['tanggal_mulai' => '2026-03-09', 'tanggal_selesai' => '2026-03-11']);

        $this->bukaRincian()
            ->assertSee('Maret 2026')
            ->assertViewHas('daftar', fn ($daftar) => $daftar->keys()->first() === 'Maret 2026');
    }

    public function test_saringan_bulan_dan_tahun_tersedia(): void
    {
        $this->usulan->update(['tanggal_mulai' => '2026-03-09', 'tanggal_selesai' => '2026-03-11']);

        $this->bukaRincian()
            ->assertViewHas('tahunTersedia', fn ($tahun) => $tahun->contains(2026))
            ->assertViewHas('jumlahBulan', fn ($bulan) => ($bulan[3] ?? 0) === 1);
    }

    public function test_saringan_periode_membatasi_daftarnya(): void
    {
        $this->usulan->update(['tanggal_mulai' => '2026-03-09', 'tanggal_selesai' => '2026-03-11']);

        $this->actingAs(User::factory()->ppk()->create())
            ->get(route('persetujuan.rincian-biaya', ['tahun' => 2026, 'bulan' => 4]))
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar) => $daftar->isEmpty());

        $this->actingAs(User::factory()->ppk()->create())
            ->get(route('persetujuan.rincian-biaya', ['tahun' => 2026, 'bulan' => 3]))
            ->assertOk()
            ->assertViewHas('daftar', fn ($daftar) => $daftar->flatten(1)->count() === 1);
    }

    // ── Menu tersendiri ──

    public function test_menu_rincian_biaya_terbuka_bagi_ppk(): void
    {
        $this->bukaRincian()
            ->assertSee('Verifikasi Rincian Biaya Perjadin')
            ->assertSee($this->usulan->no_usulan);
    }

    public function test_menu_rincian_biaya_tertutup_bagi_peran_lain(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
            ->get(route('persetujuan.rincian-biaya'))
            ->assertForbidden();
    }

    /**
     * Rincian per komponen sengaja tidak ikut: yang dikerjakan PPK di
     * sini adalah memutuskan atas dokumennya secara utuh. Memeriksa
     * angka baris demi baris pekerjaan tim keuangan.
     */
    public function test_tabel_komponen_tidak_ditampilkan(): void
    {
        $this->bukaRincian()
            ->assertDontSee('Tiket pesawat PP')
            ->assertDontSee('Uang penginapan');
    }

    public function test_menampilkan_nomor_surat_tugas_dan_total(): void
    {
        $this->bukaRincian()
            ->assertSee($this->usulan->no_tugas)
            ->assertSee('7.799.300');
    }

    public function test_menyediakan_cetak_dan_tanda_tangan(): void
    {
        $this->bukaRincian()
            ->assertSee(route('keuangan.cetak-rincian', $this->usulan->no_usulan))
            ->assertSee('Cetak');
    }

    /**
     * Transport lokal dipertanggungjawabkan lewat daftar riil, jadi ia
     * tidak boleh ikut muncul di sini — kalau muncul, nominalnya terhitung
     * dua kali pada dua dokumen.
     */
    public function test_transport_lokal_tidak_ikut_ditampilkan(): void
    {
        $this->tambahTransportLokal();

        $this->bukaRincian()->assertDontSee('Transport lokal bandara');
    }

    public function test_jumlahnya_tidak_memuat_transport_lokal(): void
    {
        $this->tambahTransportLokal();

        // 4.749.300 + 1.590.000 + 1.460.000 = 7.799.300, tanpa 300.000.
        $this->bukaRincian()->assertSee('7.799.300');
    }

    public function test_yang_belum_diperiksa_ditandai(): void
    {
        $this->bukaRincian()
            ->assertSee('belum diperiksa')
            ->assertSee('Menunggu Validasi');
    }

    /**
     * Dikelompokkan menurut sejauh mana berkasnya sudah berjalan,
     * sehingga PPK melihat mana yang menuntut tanda tangannya.
     */
    public function test_dapat_disaring_menurut_status(): void
    {
        $this->actingAs(User::factory()->ppk()->create())
            ->get(route('persetujuan.rincian-biaya', ['status' => 'ditandatangani']))
            ->assertOk()
            ->assertDontSee($this->usulan->no_usulan);
    }

    // ── Halaman daftar riil ──

    /**
     * Halaman daftar riil tidak lagi menampilkan rincian biaya di sampingnya,
     * apalagi selisih antara keduanya: total daftar riil hanya transport
     * lokal, sedangkan rincian memuat komponen lainnya.
     */
    public function test_daftar_riil_tidak_lagi_membandingkan_dengan_rencana(): void
    {
        DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 300_000,
        ]);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertDontSee('Nominal riil berbeda dari rencana biaya')
            ->assertDontSee('Sesuai rencana biaya');
    }

    public function test_daftar_riil_menautkan_ke_menu_rincian_biaya(): void
    {
        $this->actingAs(User::factory()->ppk()->create())
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee(route('persetujuan.rincian-biaya'));
    }

    public function test_usulan_tanpa_data_keuangan_tetap_terbuka(): void
    {
        $lain = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('daftar-riil.show', $lain))
            ->assertOk();
    }

    // ── Cetakan ──

    public function test_cetakan_rincian_menolak_transport_lokal(): void
    {
        $this->tambahTransportLokal();

        // Isinya PDF terkompresi, jadi yang diuji di sini permintaannya lolos
        // dan berkasnya terbentuk; penyaringan transport lokalnya sendiri
        // sudah dikunci pengujian halaman di atas.
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('keuangan.cetak-rincian', $this->usulan->no_usulan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
