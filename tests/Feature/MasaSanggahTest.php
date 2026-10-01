<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sebelum PPK menandatangani, pelaksana perjalanan diberi kesempatan
 * memeriksa nominalnya lebih dulu.
 */
class MasaSanggahTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private User $pelaksana;

    private User $timKeuangan;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        $this->ppk = User::factory()->create(['role' => User::ROLE_PPK]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_user' => $this->pelaksana->id,
        ]);
    }

    private function daftarDenganNominal(float $nominal = 900_000): DaftarRiil
    {
        return DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => $nominal,
        ]);
    }

    private function kirimKePelaksana(): DaftarRiil
    {
        $this->daftarDenganNominal();

        // Transport lokal ikut menahan pengiriman sampai diperiksa.
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));

        return DaftarRiil::firstWhere('id_peserta', $this->peserta->id);
    }

    // ── Pengiriman ke pelaksana ──

    public function test_tim_keuangan_mengirim_rincian_dan_membuka_masa_sanggah(): void
    {
        $daftar = $this->kirimKePelaksana();

        $this->assertNotNull($daftar->dikirim_ke_pegawai_at);
        $this->assertTrue($daftar->masaSanggahBerjalan());
        $this->assertSame(
            today()->addDays(DaftarRiil::HARI_MASA_SANGGAH)->toDateString(),
            $daftar->batas_sanggah->toDateString()
        );
    }

    public function test_pelaksana_diberi_tahu_saat_rincian_dikirim(): void
    {
        $this->kirimKePelaksana();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Berkas pertanggungjawaban menunggu tanda tangan Anda',
        ]);
    }

    public function test_berkas_tanpa_nominal_tidak_dapat_dikirim(): void
    {
        $this->daftarDenganNominal(0);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('error');
    }

    public function test_pelaksana_tidak_dapat_mengirim_rinciannya_sendiri(): void
    {
        $this->daftarDenganNominal();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    // ── Persetujuan pelaksana ──

    public function test_pelaksana_menyetujui_nominal(): void
    {
        $daftar = $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $daftar->refresh();

        $this->assertTrue($daftar->sudahDisetujuiPegawai());
        $this->assertTrue($daftar->siapDitandatanganiPpk());
    }

    public function test_orang_lain_tidak_dapat_menyetujui_atas_nama_pelaksana(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs(User::factory()->create())
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    public function test_rincian_yang_belum_dikirim_tidak_dapat_ditanggapi(): void
    {
        $this->daftarDenganNominal();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    // ── Sanggahan ──

    public function test_pelaksana_menyanggah_nominal_yang_tidak_sesuai(): void
    {
        $daftar = $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Transport lokal seharusnya Rp 580.000 sesuai nota terlampir.',
            ])
            ->assertSessionHas('success');

        $daftar->refresh();

        $this->assertTrue($daftar->sedangDisanggah());
        $this->assertFalse($daftar->siapDitandatanganiPpk());
        $this->assertStringContainsString('580.000', $daftar->sanggahan);
    }

    public function test_sanggahan_wajib_diuraikan(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), ['sanggahan' => 'salah'])
            ->assertSessionHasErrors('sanggahan');
    }

    public function test_tim_keuangan_diberi_tahu_saat_ada_sanggahan(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Uang penginapan belum dihitung untuk malam kedua.',
            ]);

        // Tautannya ke halaman Keuangan, tempat sanggahan tampil dan diperbaiki.
        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->timKeuangan->id,
            'judul' => 'Rincian biaya disanggah',
            'url' => route('keuangan.detail', $this->usulan),
        ]);
    }

    public function test_kirim_ulang_setelah_perbaikan_mereset_masa_sanggah(): void
    {
        $daftar = $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Nominalnya kurang dari yang saya keluarkan.',
            ]);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));

        $daftar->refresh();

        $this->assertFalse($daftar->sedangDisanggah());
        $this->assertNull($daftar->sanggahan);
        $this->assertTrue($daftar->masaSanggahBerjalan());
    }

    // ── Sanggahan rincian biaya ditindaklanjuti tim keuangan ──

    private function rincianUangHarian(float $harga = 100_000): void
    {
        $keuangan = Keuangan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'status' => Keuangan::STATUS_BELUM,
            'uang_muka' => 0,
            'sisa' => 0,
        ]);

        RincianBiaya::factory()->create([
            'id_keuangan' => $keuangan->id,
            'kategori' => 'uang_harian',
            'komponen' => 'Uang Harian Dalam Kota',
            'volume' => 1,
            'satuan' => 'OH',
            'harga_satuan' => $harga,
            'jumlah' => $harga,
            'sumber' => RincianBiaya::SUMBER_KEUANGAN,
        ]);
    }

    private function sanggahRincian(): void
    {
        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta, 'rincian']), [
                'sanggahan' => 'Uang harian dalam kota sesuai SBM Rp150.000, bukan Rp100.000.',
            ])
            ->assertSessionHas('success');
    }

    /**
     * Sanggahan atas rincian biaya tampil di halaman Keuangan — tempat
     * nominalnya diperbaiki — bersama tombol pengiriman ulangnya. Tanpa itu
     * berkasnya buntu: pelaksana menunggu, PPK tidak dapat menandatangani.
     */
    public function test_sanggahan_rincian_tampil_di_halaman_keuangan_beserta_kirim_ulang(): void
    {
        $this->rincianUangHarian();
        $this->kirimKePelaksana();
        $this->sanggahRincian();

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertSee('Disanggah pelaksana — Rincian Biaya Perjalanan Dinas')
            ->assertSee('Uang harian dalam kota sesuai SBM Rp150.000, bukan Rp100.000.')
            ->assertSee('Kirim Ulang ke Pelaksana')
            ->assertDontSee('Sudah dikirim ke pelaksana');
    }

    /**
     * Pengiriman ulang hanya membuka dokumen yang belum disetujui: daftar riil
     * yang sudah ditandatangani pelaksana tetap berlaku, sesuai panduan
     * "dokumen satunya tetap berjalan".
     */
    public function test_kirim_ulang_mempertahankan_dokumen_yang_sudah_ditandatangani_pelaksana(): void
    {
        $this->rincianUangHarian();
        $daftar = $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta, 'riil']))
            ->assertSessionHas('success');
        $this->sanggahRincian();
        $kodeRiil = $daftar->fresh()->jalur()->kodeKonfirmasi();

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $daftar->refresh();
        $this->assertTrue($daftar->jalur()->sudahDisetujui());
        $this->assertSame($kodeRiil, $daftar->jalur()->kodeKonfirmasi());
        $this->assertFalse($daftar->jalurRincian()->sedangDisanggah());
        $this->assertNull($daftar->jalurRincian()->sanggahan());
        $this->assertTrue($daftar->jalurRincian()->masaSanggahBerjalan());

        // Pemberitahuannya hanya menyebut dokumen yang perlu disikapi lagi.
        $pemberitahuan = $this->pelaksana->notifikasi()->latest('id')->first();
        $this->assertSame(route('rincian-saya.rincian-biaya'), $pemberitahuan->url);
        $this->assertStringStartsWith('Rincian biaya (Rp 100.000) perjalanan dinas', $pemberitahuan->pesan);
        $this->assertStringNotContainsString('daftar pengeluaran riil', $pemberitahuan->pesan);
    }

    /** Dokumen tanpa nominal tidak disebut, jadi tautannya menuju dokumen yang perlu disikapi. */
    public function test_pemberitahuan_hanya_menyebut_dokumen_bernominal(): void
    {
        $this->rincianUangHarian(150_000);
        $this->daftarDenganNominal(0);
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $pemberitahuan = $this->pelaksana->notifikasi()->first();
        $this->assertSame(route('rincian-saya.rincian-biaya'), $pemberitahuan->url);
        $this->assertStringNotContainsString('daftar pengeluaran riil', $pemberitahuan->pesan);
    }

    // ── Gerbang tanda tangan PPK ──

    public function test_ppk_tidak_dapat_menandatangani_sebelum_pelaksana_menanggapi(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    public function test_ppk_tidak_dapat_menandatangani_saat_masih_disanggah(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Ada komponen biaya yang terlewat dihitung.',
            ]);

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    public function test_masa_sanggah_lewat_tanpa_tanggapan_dianggap_diterima(): void
    {
        $daftar = $this->kirimKePelaksana();

        // Batas sanggah dimundurkan seolah tenggatnya sudah lewat.
        $daftar->update(['batas_sanggah' => today()->subDay()]);
        $daftar->refresh();

        $this->assertTrue($daftar->sanggahKedaluwarsa());
        $this->assertTrue($daftar->siapDitandatanganiPpk());

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNotNull($daftar->fresh()->ditandatangani_at);
    }

    /**
     * Masa sanggah yang lewat menutup sanggahan, bukan tanda tangan: tombol
     * tanda tangan tetap ada dan hanya diberi pengingat, supaya pelaksana
     * yang terlambat membuka tetap dapat menandatangani sampai PPK mengesahkan.
     */
    public function test_pelaksana_masih_dapat_menandatangani_setelah_masa_sanggah_berakhir(): void
    {
        $daftar = $this->kirimKePelaksana();
        $daftar->update(['batas_sanggah' => today()->subDay()]);

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNotNull($daftar->fresh()->disetujui_pegawai_at);
    }

    public function test_pelaksana_tidak_dapat_menyanggah_setelah_masa_sanggah_berakhir(): void
    {
        $daftar = $this->kirimKePelaksana();
        $daftar->update(['batas_sanggah' => today()->subDay()]);

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Nominal transport tidak sesuai nota yang diunggah.',
            ])
            ->assertForbidden();
    }

    // ── Halaman Rincian Saya ──

    public function test_halaman_rincian_saya_menampilkan_yang_menunggu_tanggapan(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('Setuju &amp; Tandatangani', false);
    }

    public function test_rincian_milik_orang_lain_tidak_muncul(): void
    {
        $this->kirimKePelaksana();

        $this->actingAs(User::factory()->create())
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertDontSee($this->usulan->no_usulan);
    }

    public function test_seluruh_peran_dapat_membuka_rincian_miliknya(): void
    {
        foreach ([PeranPengguna::DosenTendik, PeranPengguna::TimSdm, PeranPengguna::Pimpinan] as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('rincian-saya.daftar-riil'))
                ->assertOk();
        }
    }
}
