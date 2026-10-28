<?php

namespace Tests\Feature;

use App\Enums\BagianLaporan;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\LaporanDokumentasi;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use App\Services\FormatLaporanPerjadin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Laporan perjalanan dinas format Kemenkes: judul "tentang", Pendahuluan
 * 1–4, bagian B–E, satu foto dokumentasi opsional per bagian yang
 * tercetak pada halaman lampiran, dan tanda tangan pelaksana serta Direktur.
 */
class LaporanFormatKemenkesTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Maya Sari Wulandari']);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.01.02/F.XXX/1557/2026',
            'uraian' => 'Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan',
            'lokasi' => 'Jakarta',
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        User::factory()->create(['role' => User::ROLE_PIMPINAN, 'nama' => 'Dr. Hanung Prasetya', 'jabatan' => 'Direktur']);
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function simpan(array $isian = []): TestResponse
    {
        return $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.update', $this->usulan), $isian);
    }

    /** @return array<string, mixed> */
    private function isianLengkap(): array
    {
        return [
            'tentang' => 'Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan',
            'id_status_hasil' => StatusHasil::first()->id,
            'bagian' => [
                'umum' => 'Kegiatan diselenggarakan Ditjen SDM Kesehatan.',
                'maksud-tujuan' => 'Menyelaraskan program pendidikan vokasi.',
                'ruang-lingkup' => 'Kurikulum dan akreditasi.',
                'dasar' => 'Surat Tugas Nomor KP.01.02/F.XXX/1557/2026.',
                'kegiatan' => 'Mengikuti sidang pleno dan diskusi kelompok.',
                'hasil' => 'Tersusun rekomendasi penyelarasan kurikulum.',
                'simpulan' => 'Perlu sosialisasi di tingkat jurusan.',
                'penutup' => 'Demikian laporan ini dibuat.',
            ],
        ];
    }

    private function laporan(): LaporanPerjadin
    {
        return LaporanPerjadin::with('dokumentasi')->firstWhere('id_usulan', $this->usulan->id);
    }

    public function test_laporan_baru_memakai_format_kemenkes_dengan_isian_awal_dari_usulan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('data-format-kemenkes', false)
            ->assertSeeInOrder(['TENTANG', 'A. Pendahuluan', '1. Umum', '2. Maksud dan Tujuan', '3. Ruang Lingkup', '4. Dasar',
                'B. Kegiatan yang Dilaksanakan', 'C. Hasil yang Dicapai', 'D. Simpulan dan Saran', 'E. Penutup'])
            ->assertSee('Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan')
            ->assertSee('Surat Tugas Nomor KP.01.02/F.XXX/1557/2026')
            ->assertDontSee('Uraian Kegiatan per Hari');

        $this->assertTrue($this->laporan()->formatKemenkes());
    }

    public function test_laporan_yang_sudah_diisi_dengan_format_lama_tetap_format_lama(): void
    {
        LaporanPerjadin::create(['id_usulan' => $this->usulan->id, 'versi_format' => LaporanPerjadin::FORMAT_LAMA, 'kesimpulan' => 'Laporan lama.']);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('Uraian Kegiatan per Hari')
            ->assertDontSee('data-format-kemenkes', false);
    }

    public function test_isian_tiap_bagian_dan_foto_dokumentasi_tersimpan(): void
    {
        $this->simpan($this->isianLengkap() + ['dokumentasi' => [
            'umum' => UploadedFile::fake()->image('pembukaan.jpg', 800, 600),
            'kegiatan' => UploadedFile::fake()->image('sidang.png', 800, 600),
        ]])->assertSessionHasNoErrors();

        $laporan = $this->laporan();

        $this->assertSame('Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan', $laporan->tentang);
        $this->assertSame('Mengikuti sidang pleno dan diskusi kelompok.', $laporan->isiBagian(BagianLaporan::Kegiatan));
        $this->assertSame('Perlu sosialisasi di tingkat jurusan.', $laporan->kesimpulan);
        $this->assertSame('Demikian laporan ini dibuat.', $laporan->isiBagian(BagianLaporan::Penutup));
        $this->assertCount(2, $laporan->dokumentasi);
        Storage::disk('public')->assertExists($laporan->dokumentasiBagian(BagianLaporan::Kegiatan)->berkas);
        $this->assertNull($laporan->dokumentasiBagian(BagianLaporan::Hasil));
    }

    public function test_foto_dokumentasi_berupa_gambar_paling_besar_dua_megabita(): void
    {
        $this->simpan(['dokumentasi' => ['hasil' => UploadedFile::fake()->image('besar.jpg')->size(2049)]])
            ->assertSessionHasErrors('dokumentasi.hasil');
        $this->simpan(['dokumentasi' => ['hasil' => UploadedFile::fake()->create('notulen.pdf', 100, 'application/pdf')]])
            ->assertSessionHasErrors('dokumentasi.hasil');

        $this->assertSame(0, LaporanDokumentasi::count());
    }

    public function test_foto_dokumentasi_dapat_diganti_dan_dihapus(): void
    {
        $this->simpan(['dokumentasi' => ['hasil' => UploadedFile::fake()->image('pertama.jpg')]])->assertSessionHasNoErrors();
        $pertama = $this->laporan()->dokumentasiBagian(BagianLaporan::Hasil)->berkas;

        $this->simpan(['dokumentasi' => ['hasil' => UploadedFile::fake()->image('kedua.jpg')]])->assertSessionHasNoErrors();
        $kedua = $this->laporan()->dokumentasiBagian(BagianLaporan::Hasil)->berkas;
        Storage::disk('public')->assertMissing($pertama);
        $this->assertSame(1, LaporanDokumentasi::count());

        $this->simpan(['hapus_dokumentasi' => ['hasil' => '1']])->assertSessionHasNoErrors();
        $this->assertNull($this->laporan()->dokumentasiBagian(BagianLaporan::Hasil));
        Storage::disk('public')->assertMissing($kedua);
    }

    public function test_kirim_ke_pimpinan_mensyaratkan_bagian_wajib(): void
    {
        $this->simpan(['tentang' => 'Rapat koordinasi', 'action' => 'kirim'])
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'B. Kegiatan yang Dilaksanakan')
                && str_contains($pesan, 'C. Hasil yang Dicapai')
                && str_contains($pesan, 'D. Simpulan dan Saran'));
        $this->assertNull($this->laporan()->dikirim_at);

        $this->simpan($this->isianLengkap() + ['action' => 'kirim'])->assertSessionHas('success');
        $this->assertNotNull($this->laporan()->dikirim_at);
    }

    public function test_dokumen_tercetak_mengikuti_format_kemenkes_dengan_lampiran_dokumentasi(): void
    {
        $this->simpan($this->isianLengkap() + ['action' => 'kirim', 'dokumentasi' => [
            'kegiatan' => UploadedFile::fake()->image('sidang.jpg', 800, 600),
            'simpulan' => UploadedFile::fake()->image('diskusi.png', 800, 600),
        ]])->assertSessionHas('success');

        $laporan = $this->laporan()->load('tindakLanjut', 'pimpinan');

        $html = view('dokumen.laporan-cetak-kemenkes', app(FormatLaporanPerjadin::class)->data($this->usulan->fresh()) + [
            'laporan' => $laporan,
            'lampiran' => $laporan->lampiranDokumentasi(),
            'qrPelaksana' => null,
            'qrPimpinan' => null,
        ])->render();

        $this->assertStringContainsString('kop-surat-poltekkes.jpg', $html);
        $this->assertStringContainsString('Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan', $html);
        foreach (['A. Pendahuluan', 'B. Kegiatan yang Dilaksanakan', 'C. Hasil yang Dicapai', 'D. Simpulan dan Saran', 'E. Penutup',
            'Mengetahui,', 'Yang membuat laporan', 'Maya Sari Wulandari', 'LAMPIRAN'] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
        $this->assertStringContainsString('Dokumentasi: Lampiran 1', $html);
        $this->assertStringContainsString('Lampiran 2: D. Simpulan dan Saran', $html);
        $this->assertSame(1, substr_count($html, 'data:image/jpeg;base64,'));
        $this->assertSame(1, substr_count($html, 'data:image/png;base64,'));

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.cetak', $this->usulan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pimpinan_membaca_laporan_format_kemenkes_beserta_dokumentasinya(): void
    {
        $this->simpan($this->isianLengkap() + ['action' => 'kirim', 'dokumentasi' => [
            'kegiatan' => UploadedFile::fake()->image('sidang.jpg'),
        ]])->assertSessionHas('success');

        $berkas = $this->laporan()->dokumentasiBagian(BagianLaporan::Kegiatan)->berkas;

        $this->actingAs(User::where('jabatan', 'Direktur')->sole())
            ->get(route('laporan-perjadin.show', $this->usulan))
            ->assertOk()
            ->assertSee('data-format-kemenkes', false)
            ->assertSeeInOrder(['TENTANG', 'A. Pendahuluan', '1. Umum', 'B. Kegiatan yang Dilaksanakan', 'Mengikuti sidang pleno'])
            ->assertSee(route('berkas.lihat', $berkas));
    }
}
