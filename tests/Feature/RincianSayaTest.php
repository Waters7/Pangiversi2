<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\JalurPersetujuan;
use App\Services\PemantauBerkas;
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

    // ── Tanda tangan pelaksana tidak kedaluwarsa ──

    /**
     * Masa sanggah yang lewat menutup sanggahan, bukan tanda tangan: dulu
     * tombolnya ikut hilang, sehingga pelaksana yang terlambat membuka tidak
     * pernah dapat menandatangani dan QR-nya tidak pernah terbit.
     */
    public function test_tombol_tanda_tangan_tetap_ada_setelah_masa_sanggah_lewat(): void
    {
        $this->berkasSampaiKePelaksana();
        $this->daftar()->update(['batas_sanggah' => today()->subDay()]);

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Setuju &amp; Tandatangani', false)
            ->assertDontSee('Sanggah Nominal')
            ->assertSee('Anda tetap dapat menandatangani dokumen ini');

        // Masih tergolong perlu tanggapan, bukan tersembunyi di "Lainnya".
        $this->assertSame(1, $halaman->viewData('jumlah')['perlu-tanggapan']);
    }

    public function test_pelaksana_dapat_menandatangani_setelah_masa_sanggah_lewat(): void
    {
        $this->berkasSampaiKePelaksana();
        $this->daftar()->update(['batas_sanggah' => today()->subDay()]);

        $this->setujui('rincian')->assertSessionHas('success');

        $this->assertNotNull($this->daftar()->rincian_disetujui_at);
    }

    public function test_sanggahan_tertutup_setelah_masa_sanggah_lewat(): void
    {
        $this->berkasSampaiKePelaksana();
        $this->daftar()->update(['batas_sanggah' => today()->subDay()]);

        $this->sanggah('rincian', 'Uang harian dihitung empat hari, seharusnya tiga.')
            ->assertForbidden();
    }

    // ── Sudah dicek tim keuangan, belum dikirim ──

    /**
     * Berkas yang nominalnya sudah diperiksa tim keuangan tetapi belum
     * dikirim dulu tidak terlihat sama sekali dari sisi pelaksana — ia
     * mengira rinciannya belum disentuh siapa pun.
     */
    public function test_rincian_yang_sudah_dicek_tampil_sebelum_dikirim(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Sudah Dicek Tim Keuangan')
            ->assertSee('Sudah dicek tim keuangan')
            ->assertSee('Dicek Tim Keuangan');

        $this->assertSame(1, $this->jumlahTampil($halaman));
    }

    public function test_rincian_yang_belum_dicek_belum_tampil(): void
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk();

        $this->assertSame(0, $this->jumlahTampil($halaman));
    }

    public function test_status_dicek_berganti_setelah_dikirim(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Menunggu Tanggapan Pelaksana')
            ->assertDontSee('Sudah Dicek Tim Keuangan');
    }

    // ── Pemantauan berkas ──

    /** @return array<string, array<string, mixed>> Butir pemantauan, bertaut labelnya. */
    private function butirPantau(TestResponse $halaman, string $bagian): array
    {
        return collect($halaman->viewData('daftar')->flatten(1)->first()['pantau'][$bagian])
            ->keyBy('label')->all();
    }

    /**
     * Begitu berkas sampai di meja pelaksana, panel pemantauan sudah
     * menyebut apa yang selesai dan apa yang masih ditunggu — tanda tangan
     * maupun pembayaran.
     */
    public function test_panel_pemantauan_menyebut_tahap_yang_selesai_dan_yang_ditunggu(): void
    {
        $this->berkasSampaiKePelaksana();

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Pemantauan Berkas')
            ->assertSee('Status Tanda Tangan')
            ->assertSee('Status Pembayaran')
            ->assertSee('Menunggu tanda tangan Anda')
            ->assertSee('Laporan belum dikirim ke pimpinan')
            ->assertSee('Terbit setelah kedua dokumen ditandatangani PPK');

        $tandaTangan = $this->butirPantau($halaman, 'tanda_tangan');
        $pembayaran = $this->butirPantau($halaman, 'pembayaran');

        $this->assertSame(PemantauBerkas::SELESAI, $tandaTangan['Dicek tim keuangan']['keadaan']);
        $this->assertSame($this->timKeuangan->nama, $tandaTangan['Dicek tim keuangan']['oleh']);
        $this->assertSame(PemantauBerkas::SELESAI, $tandaTangan['Dikirim ke Anda']['keadaan']);
        $this->assertSame(PemantauBerkas::MENUNGGU, $tandaTangan['Rincian biaya · tanda tangan Anda']['keadaan']);
        $this->assertSame(PemantauBerkas::MENUNGGU, $tandaTangan['Rincian biaya · tanda tangan PPK']['keadaan']);
        $this->assertSame('Setelah tanda tangan Anda', $tandaTangan['Rincian biaya · tanda tangan PPK']['keterangan']);
        $this->assertSame(PemantauBerkas::MENUNGGU, $tandaTangan['Daftar riil · tanda tangan Anda']['keadaan']);

        $this->assertSame(PemantauBerkas::MENUNGGU, $pembayaran['Uang muka']['keadaan']);
        $this->assertSame('Setelah uang muka dibayarkan', $pembayaran['Pelunasan']['keterangan']);
        $this->assertSame('Setelah daftar riil ditandatangani PPK', $pembayaran['Transport lokal']['keterangan']);
    }

    /**
     * Setelah seluruh tahap terlewati — kedua dokumen disahkan PPK, laporan
     * dikonfirmasi Direktur, nominatif diterima, dan pelunasan dibayarkan —
     * seluruh butir berkeadaan selesai beserta kode dan tanggalnya.
     */
    public function test_panel_pemantauan_lengkap_setelah_seluruh_tahap_terlewati(): void
    {
        $this->berkasSampaiKePelaksana();
        $ppk = User::factory()->ppk()->create();
        $this->terbitkanNominatif($this->usulan, $ppk);
        $this->konfirmasiLaporan($this->usulan);
        $this->daftar()->update([
            'kode_konfirmasi' => 'KNF-UJI1',
            'rincian_kode_konfirmasi' => 'KNF-UJI2',
            'kode_verifikasi' => 'PPK-UJI1',
            'rincian_kode_verifikasi' => 'PPK-UJI2',
        ]);

        $keuangan = $this->usulan->keuangan;
        $keuangan->update([
            'status' => Keuangan::STATUS_LUNAS,
            'tanggal_transfer' => today()->subDays(5),
            'tanggal_pelunasan' => today(),
            'uang_muka' => 1_500_000,
            'sisa' => 250_000,
        ]);
        $keuangan->konfirmasiPelunasan();

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee('Kode verifikasi PPK-UJI1')
            ->assertSee('Syarat pelunasan terpenuhi')
            ->assertSee('Anda tercantum di dalamnya')
            ->assertSee('Dibayar bersama pelunasan')
            ->assertSee('Rp 1.500.000')
            ->assertSee('Rp 250.000');

        $tandaTangan = $this->butirPantau($halaman, 'tanda_tangan');
        $pembayaran = $this->butirPantau($halaman, 'pembayaran');

        foreach (['Rincian biaya · tanda tangan Anda', 'Rincian biaya · tanda tangan PPK', 'Daftar riil · tanda tangan Anda',
            'Daftar riil · tanda tangan PPK', 'Laporan perjadin · konfirmasi Direktur', 'Daftar nominatif · terbit',
            'Daftar nominatif · tanda tangan PPK', 'Daftar nominatif · diterima tim keuangan'] as $label) {
            $this->assertSame(PemantauBerkas::SELESAI, $tandaTangan[$label]['keadaan'], $label);
        }

        $this->assertSame($ppk->nama, $tandaTangan['Daftar riil · tanda tangan PPK']['oleh']);
        $this->assertSame(PemantauBerkas::SELESAI, $pembayaran['Uang muka']['keadaan']);
        $this->assertSame(PemantauBerkas::SELESAI, $pembayaran['Pelunasan']['keadaan']);
        $this->assertStringStartsWith('Kode bendahara BND-', $pembayaran['Pelunasan']['keterangan']);
        $this->assertSame(PemantauBerkas::SELESAI, $pembayaran['Transport lokal']['keadaan']);
    }

    /** Sanggahan dan pengembalian laporan ditandai sebagai perhatian, bukan sekadar menunggu. */
    public function test_panel_pemantauan_menandai_sanggahan_dan_laporan_yang_dikembalikan(): void
    {
        $this->berkasSampaiKePelaksana();
        $this->sanggah(JalurPersetujuan::RINCIAN, 'Uang harian dihitung 4 hari, SPD menyebut 3 hari.');

        $direktur = User::factory()->create(['role' => User::ROLE_PIMPINAN, 'jabatan' => 'Direktur']);
        $laporan = $this->konfirmasiLaporan($this->usulan, $direktur);
        $laporan->batalkanKonfirmasi();
        $laporan->kembalikan($direktur, 'Uraian kegiatan hari kedua kosong.');

        // Uang muka sudah cair, jadi satu-satunya penahan pelunasan adalah laporannya.
        $this->usulan->keuangan->update(['status' => Keuangan::STATUS_SEBAGIAN, 'tanggal_transfer' => today()->subDays(3)]);

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Anda menyanggah')
            ->assertSee('Dikembalikan pimpinan');

        $tandaTangan = $this->butirPantau($halaman, 'tanda_tangan');

        $this->assertSame(PemantauBerkas::PERHATIAN, $tandaTangan['Rincian biaya · tanda tangan Anda']['keadaan']);
        $this->assertSame(PemantauBerkas::PERHATIAN, $tandaTangan['Laporan perjadin · konfirmasi Direktur']['keadaan']);
        $this->assertSame('Menunggu konfirmasi laporan oleh Direktur', $this->butirPantau($halaman, 'pembayaran')['Pelunasan']['keterangan']);
    }

    /** Perjalanan tanpa transport lokal: daftar riilnya tidak ditagih tanda tangan maupun pembayaran. */
    public function test_panel_pemantauan_menandai_transport_lokal_yang_tidak_ada(): void
    {
        $this->berkasSampaiKePelaksana();
        $this->daftar()->update(['total_riil' => 0]);

        $halaman = $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.rincian-biaya'))
            ->assertOk()
            ->assertSee('Tidak ada transport lokal');

        $this->assertSame(PemantauBerkas::TIDAK_PERLU, $this->butirPantau($halaman, 'tanda_tangan')['Daftar riil transport lokal']['keadaan']);
        $this->assertSame(PemantauBerkas::TIDAK_PERLU, $this->butirPantau($halaman, 'pembayaran')['Transport lokal']['keadaan']);
    }
}
