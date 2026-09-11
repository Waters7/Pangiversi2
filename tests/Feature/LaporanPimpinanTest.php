<?php

namespace Tests\Feature;

use App\Enums\StatusLaporanPerjadin;
use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use App\Services\FormatLaporanPerjadin;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Laporan perjalanan dinas berakhir di meja pimpinan.
 *
 * Pelaksana mengirim laporan yang sudah selesai; pimpinan mengonfirmasi dan
 * menandatanganinya, atau mengembalikannya untuk direvisi. Kedua pihak
 * meninggalkan kode QR, dan konfirmasi pimpinan menjadi syarat pelunasan.
 */
class LaporanPimpinanTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $pimpinan;

    private User $bendahara;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'Pelaksana Perjadin',
            'nip' => '199310182025061003',
        ]);

        $this->pimpinan = User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'nama' => 'Dr. Hanung Prasetya',
            'nip' => '197104041994031002',
            'jabatan' => 'Direktur',
        ]);

        $this->bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'TGS-2026-001',
            'no_spd' => 'KU.02.04/F.XXX.8/77/2026',
            'lokasi' => 'Jakarta',
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);

        Keuangan::factory()->uangMuka()->create(['id_usulan' => $this->usulan->id]);
    }

    /** Isi laporan tersimpan sebagai draf, belum dikirim. */
    private function laporanTerisi(): LaporanPerjadin
    {
        $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.update', $this->usulan), [
            'id_status_hasil' => StatusHasil::first()->id,
            'kegiatan' => [today()->subDays(5)->toDateString() => 'Mengikuti rapat koordinasi.'],
            'tindak_lanjut' => [[
                'uraian' => 'Menyusun draf pedoman internal.',
                'penanggung_jawab' => 'Subbag Umum',
                'target_selesai' => today()->addMonth()->toDateString(),
                'status' => StatusTindakLanjut::Rencana->value,
            ]],
        ]);

        return LaporanPerjadin::firstOrFail();
    }

    /** Menyelesaikan laporan sekaligus mengirimnya ke pimpinan. */
    private function laporanDikirim(): LaporanPerjadin
    {
        $this->laporanTerisi();
        $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.selesaikan', $this->usulan));

        return LaporanPerjadin::firstOrFail();
    }

    private function laporanDikonfirmasi(): LaporanPerjadin
    {
        $this->laporanDikirim();
        $this->actingAs($this->pimpinan)->put(route('laporan-perjadin.konfirmasi', $this->usulan));

        return LaporanPerjadin::firstOrFail();
    }

    // ── Mengirim ke pimpinan ──

    public function test_laporan_belum_selesai_tidak_dapat_dikirim(): void
    {
        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.kirim', $this->usulan))
            ->assertSessionHas('error');

        $this->assertNull(LaporanPerjadin::first()?->dikirim_at);
    }

    public function test_menyelesaikan_laporan_langsung_mengirimnya_ke_pimpinan(): void
    {
        $this->laporanTerisi();

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.selesaikan', $this->usulan))
            ->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertNotNull($laporan->dikirim_at);
        $this->assertMatchesRegularExpression('/^LPK-[A-Z0-9]{10}$/', $laporan->kode_pelaksana);
        $this->assertSame(StatusLaporanPerjadin::MenungguKonfirmasi, $laporan->status());
    }

    /** Tombol "Simpan & Kirim" menyimpan isi, menyelesaikan, dan mengirim sekaligus. */
    public function test_simpan_dan_kirim_dari_formulir_dalam_satu_langkah(): void
    {
        $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.update', $this->usulan), [
            'action' => 'kirim',
            'id_status_hasil' => StatusHasil::first()->id,
            'kegiatan' => [today()->subDays(5)->toDateString() => 'Mengikuti rapat koordinasi.'],
            'tempat' => [today()->subDays(5)->toDateString() => 'Kantor Dinas Kesehatan'],
            'tindak_lanjut' => [['uraian' => 'Menyusun draf pedoman.', 'status' => StatusTindakLanjut::Rencana->value]],
        ])->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertNotNull($laporan->diselesaikan_at);
        $this->assertNotNull($laporan->dikirim_at);
        $this->assertSame('Kantor Dinas Kesehatan', $laporan->kegiatan()->first()->tempat);
    }

    public function test_simpan_draf_tidak_mengirim(): void
    {
        $this->laporanTerisi();

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertNull($laporan->diselesaikan_at);
        $this->assertNull($laporan->dikirim_at);
    }

    public function test_simpan_dan_kirim_ditolak_bila_isinya_belum_lengkap(): void
    {
        $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.update', $this->usulan), [
            'action' => 'kirim',
            'kegiatan' => [today()->subDays(5)->toDateString() => 'Mengikuti rapat koordinasi.'],
        ])->assertSessionHas('error');

        $this->assertNull(LaporanPerjadin::firstOrFail()->dikirim_at);
    }

    public function test_formulir_menanyakan_ulang_sebelum_mengirim(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('Simpan &amp; Kirim ke Pimpinan', false)
            ->assertSee('Simpan dan kirim laporan ke pimpinan?')
            ->assertSee('Ya, Kirim ke Pimpinan')
            ->assertSee('Simpan Draf')
            ->assertDontSee('Nyatakan Laporan Selesai');
    }

    public function test_pimpinan_diberi_tahu_saat_laporan_dikirim(): void
    {
        $this->laporanDikirim();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pimpinan->id,
            'judul' => 'Laporan perjadin menunggu konfirmasi',
        ]);
    }

    public function test_laporan_terkunci_setelah_dikirim(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.update', $this->usulan), ['kesimpulan' => 'Diubah diam-diam.'])
            ->assertForbidden();
    }

    public function test_pelaksana_dapat_menarik_laporan_yang_belum_diputuskan(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.buka', $this->usulan))
            ->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertNull($laporan->dikirim_at);
        $this->assertNull($laporan->kode_pelaksana);
        $this->assertSame(StatusLaporanPerjadin::Draf, $laporan->status());
    }

    // ── Meja pimpinan ──

    public function test_menu_pimpinan_hanya_terbuka_bagi_yang_berwenang(): void
    {
        $this->actingAs($this->pimpinan)->get(route('laporan-perjadin.index'))->assertOk();
        $this->actingAs($this->pelaksana)->get(route('laporan-perjadin.index'))->assertForbidden();
        $this->actingAs($this->bendahara)->get(route('laporan-perjadin.index'))->assertForbidden();
    }

    public function test_sidebar_pimpinan_memuat_tiga_submenu(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Daftar Laporan Perjadin')
            ->assertSee('Status Konfirmasi Laporan')
            ->assertSee(route('laporan-perjadin.tindak-lanjut'), false);

        $this->actingAs($this->pelaksana)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('laporan-perjadin.index'), false);
    }

    public function test_daftar_pimpinan_mendahulukan_laporan_yang_menunggu(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->get(route('laporan-perjadin.index'))
            ->assertOk()
            ->assertSee('Pelaksana Perjadin')
            ->assertSee('Menunggu Konfirmasi Pimpinan')
            ->assertSee(route('laporan-perjadin.show', $this->usulan->no_usulan), false);
    }

    public function test_halaman_status_menghitung_tiap_tahap(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->get(route('laporan-perjadin.status'))
            ->assertOk()
            ->assertSee('Status Konfirmasi Laporan Perjadin')
            ->assertSee('Menunggu Konfirmasi Pimpinan');
    }

    public function test_tindak_lanjut_pimpinan_memuat_laporan_yang_dikirim(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->get(route('laporan-perjadin.tindak-lanjut'))
            ->assertOk()
            ->assertSee('Menyusun draf pedoman internal.');
    }

    public function test_sidebar_pimpinan_menghitung_laporan_yang_menunggu(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 berkas menunggu tindakan Anda');
    }

    // ── Konfirmasi ──

    public function test_pimpinan_mengonfirmasi_dan_menerbitkan_kode_pimpinan(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->put(route('laporan-perjadin.konfirmasi', $this->usulan), ['catatan' => 'Laporan lengkap.'])
            ->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertNotNull($laporan->dikonfirmasi_at);
        $this->assertSame($this->pimpinan->id, $laporan->id_pimpinan);
        $this->assertMatchesRegularExpression('/^PIM-[A-Z0-9]{10}$/', $laporan->kode_pimpinan);
        $this->assertSame(StatusLaporanPerjadin::Dikonfirmasi, $laporan->status());
    }

    /**
     * Laporan hanya ditandatangani Direktur Poltekkes Kemenkes Manado.
     * Wakil direktur tetap dapat membaca dan mengembalikannya.
     */
    public function test_hanya_direktur_yang_dapat_mengonfirmasi(): void
    {
        $this->laporanDikirim();

        $wadir = User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'nama' => 'Wakil Direktur II',
            'jabatan' => 'Wakil Direktur II',
        ]);

        $this->actingAs($wadir)
            ->put(route('laporan-perjadin.konfirmasi', $this->usulan))
            ->assertForbidden();

        $this->actingAs($wadir)
            ->get(route('laporan-perjadin.show', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('hanya ditandatangani')
            ->assertDontSee('Konfirmasi Laporan Ini')
            ->assertSee('Kembalikan untuk Revisi');

        $this->actingAs($wadir)
            ->put(route('laporan-perjadin.kembalikan', $this->usulan), ['catatan' => 'Lengkapi.'])
            ->assertSessionHas('success');
    }

    public function test_dokumen_menyebut_direktur_sebagai_satu_satunya_penandatangan(): void
    {
        $laporan = $this->laporanDikonfirmasi();

        $html = view('dokumen.laporan-cetak', app(FormatLaporanPerjadin::class)->data($this->usulan) + [
            'laporan' => $laporan->load('kegiatan', 'tindakLanjut', 'pimpinan'),
            'qrPelaksana' => null,
            'qrPimpinan' => null,
        ])->render();

        $this->assertStringContainsString('Direktur Poltekkes Kemenkes Manado', $html);
        $this->assertStringNotContainsString('Wakil Direktur', $html);
        $this->assertStringContainsString('Yang membuat laporan', $html);
        // Kedua kolom memakai kotak tanda tangan yang sama tingginya.
        $this->assertSame(2, substr_count($html, 'class="kotak-ttd"'));
    }

    public function test_pelaksana_diberi_tahu_setelah_dikonfirmasi(): void
    {
        $this->laporanDikonfirmasi();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Laporan perjadin dikonfirmasi pimpinan',
        ]);
    }

    public function test_laporan_yang_belum_dikirim_tidak_dapat_dikonfirmasi(): void
    {
        $this->laporanTerisi();

        $this->actingAs($this->pimpinan)
            ->put(route('laporan-perjadin.konfirmasi', $this->usulan))
            ->assertSessionHas('error');

        $this->assertNull(LaporanPerjadin::firstOrFail()->dikonfirmasi_at);
    }

    public function test_laporan_terkunci_setelah_dikonfirmasi(): void
    {
        $this->laporanDikonfirmasi();

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.buka', $this->usulan))
            ->assertForbidden();
    }

    public function test_pimpinan_dapat_mencabut_konfirmasi_sebelum_pelunasan(): void
    {
        $this->laporanDikonfirmasi();

        $this->actingAs($this->pimpinan)
            ->delete(route('laporan-perjadin.batal-konfirmasi', $this->usulan))
            ->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertNull($laporan->dikonfirmasi_at);
        $this->assertNull($laporan->kode_pimpinan);
        $this->assertSame(StatusLaporanPerjadin::MenungguKonfirmasi, $laporan->status());
    }

    // ── Revisi ──

    public function test_pengembalian_wajib_membawa_arahan(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->put(route('laporan-perjadin.kembalikan', $this->usulan), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');
    }

    public function test_laporan_yang_dikembalikan_terbuka_lagi_bagi_pelaksana(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->put(route('laporan-perjadin.kembalikan', $this->usulan), ['catatan' => 'Lengkapi uraian hari kedua.'])
            ->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertSame(StatusLaporanPerjadin::PerluRevisi, $laporan->status());
        $this->assertNull($laporan->kode_pelaksana);
        $this->assertTrue($laporan->bolehDisunting());

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('Dikembalikan pimpinan untuk direvisi')
            ->assertSee('Lengkapi uraian hari kedua.');

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'judul' => 'Laporan perjadin perlu direvisi',
        ]);
    }

    public function test_laporan_revisi_dapat_dikirim_ulang(): void
    {
        $this->laporanDikirim();
        $this->actingAs($this->pimpinan)
            ->put(route('laporan-perjadin.kembalikan', $this->usulan), ['catatan' => 'Perbaiki.']);

        // Setelah revisi, menyelesaikan berarti mengirim ulang.
        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.selesaikan', $this->usulan))
            ->assertSessionHas('success');

        $laporan = LaporanPerjadin::firstOrFail();

        $this->assertSame(StatusLaporanPerjadin::MenungguKonfirmasi, $laporan->status());
        $this->assertNull($laporan->dikembalikan_at);
        $this->assertNotNull($laporan->kode_pelaksana);
    }

    // ── QR dan verifikasi ──

    public function test_dokumen_memuat_qr_kedua_pihak_setelah_dikonfirmasi(): void
    {
        $laporan = $this->laporanDikonfirmasi();

        $html = view('dokumen.laporan-cetak', app(FormatLaporanPerjadin::class)->data($this->usulan) + [
            'laporan' => $laporan->load('kegiatan', 'tindakLanjut', 'pimpinan'),
            'qrPelaksana' => app(QrCodeService::class)->dataUri($laporan->urlKonfirmasiPelaksana()),
            'qrPimpinan' => app(QrCodeService::class)->dataUri($laporan->urlKonfirmasiPimpinan()),
        ])->render();

        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringContainsString($laporan->kode_pelaksana, $html);
        $this->assertStringContainsString($laporan->kode_pimpinan, $html);
        $this->assertStringContainsString('Dr. Hanung Prasetya', $html);
    }

    public function test_qr_pelaksana_menunjuk_halaman_verifikasi_dengan_isi_yang_diminta(): void
    {
        $laporan = $this->laporanDikirim();

        $this->assertStringEndsWith('/verifikasi/'.$laporan->kode_pelaksana, $laporan->urlKonfirmasiPelaksana());

        $this->get(route('verifikasi.tampil', $laporan->kode_pelaksana))
            ->assertOk()
            ->assertSee('Laporan Perjalanan Dinas Terverifikasi')
            ->assertSee('Nomor Surat')
            ->assertSee('KU.02.04/F.XXX.8/77/2026')
            ->assertSee('Tanggal Perjalanan Dinas')
            ->assertSee('Tanggal Pembuatan Laporan')
            ->assertSee($laporan->dikirim_at->translatedFormat('d F Y'))
            ->assertSee('Nama Pelaksana')
            ->assertSee('Pelaksana Perjadin');
    }

    public function test_qr_pimpinan_menunjuk_halaman_verifikasi_dengan_nama_dan_tanggal(): void
    {
        $laporan = $this->laporanDikonfirmasi();

        $this->get(route('verifikasi.tampil', $laporan->kode_pimpinan))
            ->assertOk()
            ->assertSee('Nama Pimpinan')
            ->assertSee('Dr. Hanung Prasetya')
            ->assertSee('Tanggal Konfirmasi Tanda Tangan')
            ->assertSee($laporan->dikonfirmasi_at->translatedFormat('d F Y'));
    }

    public function test_kode_yang_dicabut_tidak_lagi_terverifikasi(): void
    {
        $laporan = $this->laporanDikonfirmasi();
        $kode = $laporan->kode_pimpinan;

        $this->actingAs($this->pimpinan)->delete(route('laporan-perjadin.batal-konfirmasi', $this->usulan));

        $this->get(route('verifikasi.tampil', $kode))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terverifikasi');
    }

    public function test_pimpinan_dapat_mengunduh_dokumen_laporan(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->pimpinan)
            ->get(route('dokumen.laporan.cetak', $this->usulan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // ── Syarat pelunasan ──

    /**
     * @return array<string, mixed>
     */
    private function isianPelunasan(): array
    {
        return [
            'tanggal_pelunasan' => today()->toDateString(),
            'bukti_pelunasan' => UploadedFile::fake()->create('bukti.pdf', 50, 'application/pdf'),
        ];
    }

    private function siapkanNominatif(): void
    {
        // Pelunasan menuntut pelaksananya sendiri tercantum pada daftar:
        // rincian biaya dan daftar riilnya sudah ditandatangani PPK.
        $this->tandatanganiBerkas($this->usulan);

        DaftarNominatif::create([
            'no_tugas' => $this->usulan->no_tugas,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);
    }

    public function test_pelunasan_ditolak_selama_laporan_belum_dikonfirmasi(): void
    {
        $this->siapkanNominatif();
        $this->laporanDikirim();

        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-sisa', $this->usulan), $this->isianPelunasan())
            ->assertSessionHas('error');

        $this->assertFalse($this->usulan->keuangan->fresh()->sudahLunas());
    }

    public function test_pelunasan_berjalan_setelah_laporan_dikonfirmasi(): void
    {
        $this->siapkanNominatif();
        $this->laporanDikonfirmasi();

        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-sisa', $this->usulan), $this->isianPelunasan())
            ->assertSessionMissing('error');

        $this->assertTrue($this->usulan->keuangan->fresh()->sudahLunas());
    }

    public function test_konfirmasi_tidak_dapat_dicabut_setelah_pelunasan(): void
    {
        $this->siapkanNominatif();
        $this->laporanDikonfirmasi();
        $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-sisa', $this->usulan), $this->isianPelunasan());

        $this->actingAs($this->pimpinan)
            ->delete(route('laporan-perjadin.batal-konfirmasi', $this->usulan))
            ->assertSessionHas('error');

        $this->assertNotNull(LaporanPerjadin::firstOrFail()->dikonfirmasi_at);
    }

    public function test_halaman_keuangan_menjelaskan_syarat_pelunasan(): void
    {
        $this->laporanDikirim();

        $this->actingAs($this->bendahara)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Pelunasan belum dapat dibayarkan');
    }
}
