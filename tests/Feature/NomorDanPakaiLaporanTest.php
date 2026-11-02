<?php

namespace Tests\Feature;

use App\Enums\BagianLaporan;
use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Nomor laporan perjalanan dinas, dan memakai laporan rekan lewat nomornya
 * bila nomor surat tugas, waktu pelaksanaan, dan lokasi tugasnya sama.
 */
class NomorDanPakaiLaporanTest extends TestCase
{
    use RefreshDatabase;

    private const SURAT_TUGAS = 'KP.01.02/F.XXX/1557/2026';

    private User $pelaksana;

    private Usulan $usulan;

    private User $rekan;

    private Usulan $usulanRekan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-11-01 09:00:00');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Maya Sari Wulandari']);
        $this->rekan = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Rina Kartika Dewi']);

        $this->usulan = $this->usulanUntuk($this->pelaksana);
        $this->usulanRekan = $this->usulanUntuk($this->rekan);
    }

    private function usulanUntuk(User $pelaksana): Usulan
    {
        return Usulan::factory()->create([
            'id_user' => $pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => self::SURAT_TUGAS,
            'lokasi' => 'Jakarta',
            'id_lokasi' => null,
            'tanggal_mulai' => '2026-10-24',
            'tanggal_selesai' => '2026-10-26',
        ]);
    }

    /** Laporan rekan yang sudah lengkap, berfoto, dan bertindak lanjut. */
    private function laporanRekanLengkap(): LaporanPerjadin
    {
        $laporan = LaporanPerjadin::untukUsulan($this->usulanRekan);
        $laporan->update([
            'tentang' => 'Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan',
            'umum' => 'Kegiatan diselenggarakan Ditjen SDM Kesehatan.',
            'kegiatan_dilaksanakan' => 'Mengikuti sidang pleno dan diskusi kelompok.',
            'hasil' => 'Tersusun rekomendasi penyelarasan kurikulum.',
            'kesimpulan' => 'Perlu sosialisasi di tingkat jurusan.',
            'id_status_hasil' => StatusHasil::first()->id,
        ]);
        $laporan->dokumentasi()->create([
            'bagian' => BagianLaporan::Kegiatan->value,
            'berkas' => UploadedFile::fake()->image('sidang.jpg')->store('dokumen/laporan-dokumentasi', 'public'),
        ]);
        $laporan->tindakLanjut()->create([
            'urutan' => 1,
            'uraian' => 'Sosialisasi hasil rapat kepada program studi',
            'penanggung_jawab' => 'Ketua Jurusan',
            'target_selesai' => '2026-11-16',
            'status' => StatusTindakLanjut::Rencana->value,
        ]);

        return $laporan;
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function pakai(array $isian): TestResponse
    {
        return $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.pakai', $this->usulan), $isian);
    }

    private function galat(): string
    {
        return (string) session('errors')?->first('nomor_acuan');
    }

    public function test_laporan_bernomor_urut_per_tahun_dan_dapat_dicari(): void
    {
        $pertama = LaporanPerjadin::untukUsulan($this->usulan);
        $kedua = LaporanPerjadin::untukUsulan($this->usulanRekan);

        $this->travelTo('2027-01-04 08:00:00');
        $tahunBaru = LaporanPerjadin::untukUsulan($this->usulanUntuk($this->pelaksana));

        $this->assertSame('LPD-2026-0001', $pertama->nomor_laporan);
        $this->assertSame('LPD-2026-0002', $kedua->nomor_laporan);
        $this->assertSame('LPD-2027-0001', $tahunBaru->nomor_laporan);
        $this->assertSame('LPD-2026-0001', LaporanPerjadin::untukUsulan($this->usulan)->nomor_laporan);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('LPD-2026-0001');

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.index', ['cari' => 'LPD-2026-0001']))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('data-nomor-laporan', false);
    }

    public function test_isi_laporan_rekan_setugas_dipakai_lewat_nomornya(): void
    {
        $acuan = $this->laporanRekanLengkap();
        $fotoAcuan = $acuan->dokumentasi()->sole()->berkas;

        $milikSendiri = LaporanPerjadin::untukUsulan($this->usulan);
        $fotoLama = UploadedFile::fake()->image('lama.jpg')->store('dokumen/laporan-dokumentasi', 'public');
        $milikSendiri->dokumentasi()->create(['bagian' => BagianLaporan::Hasil->value, 'berkas' => $fotoLama]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertSee('data-calon-acuan="'.$acuan->nomor_laporan.'"', false);

        $this->pakai(['nomor_acuan' => ' lpd-2026-0001 '])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $laporan = $milikSendiri->fresh(['dokumentasi', 'tindakLanjut']);
        $this->assertSame($acuan->id, $laporan->id_laporan_acuan);
        $this->assertSame('Rapat Koordinasi Nasional Pendidikan Vokasi Kesehatan', $laporan->tentang);
        $this->assertSame('Mengikuti sidang pleno dan diskusi kelompok.', $laporan->isiBagian(BagianLaporan::Kegiatan));
        $this->assertSame($acuan->id_status_hasil, $laporan->id_status_hasil);
        $this->assertSame(['Sosialisasi hasil rapat kepada program studi'], $laporan->tindakLanjut->pluck('uraian')->all());

        // Foto disalin ke berkas tersendiri; foto lama laporan ini dilepas.
        $foto = $laporan->dokumentasiBagian(BagianLaporan::Kegiatan);
        $this->assertNotSame($fotoAcuan, $foto->berkas);
        Storage::disk('public')->assertExists([$foto->berkas, $fotoAcuan]);
        Storage::disk('public')->assertMissing($fotoLama);
        $this->assertNull($laporan->dokumentasiBagian(BagianLaporan::Hasil));

        // Laporan rekan tidak berubah dan tetap milik rekan.
        $this->assertCount(1, $acuan->fresh()->tindakLanjut);
        $this->assertNull($acuan->fresh()->id_laporan_acuan);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.show', $this->usulan))
            ->assertSee('Isinya memakai laporan '.$acuan->nomor_laporan.' milik Rina Kartika Dewi');
    }

    public function test_laporan_rekan_hanya_dapat_dipakai_bila_surat_tugas_waktu_dan_lokasinya_sama(): void
    {
        $acuan = $this->laporanRekanLengkap();
        LaporanPerjadin::untukUsulan($this->usulan);

        $this->usulanRekan->update(['no_tugas' => 'KP.01.02/F.XXX/9999/2026']);
        $this->pakai(['nomor_acuan' => $acuan->nomor_laporan])->assertSessionHasErrors('nomor_acuan');
        $this->assertStringContainsString('Surat tugasnya berbeda', $this->galat());

        $this->usulanRekan->update(['no_tugas' => 'kp.01.02/f.xxx/1557/2026 ', 'tanggal_selesai' => '2026-10-27']);
        $this->pakai(['nomor_acuan' => $acuan->nomor_laporan])->assertSessionHasErrors('nomor_acuan');
        $this->assertStringContainsString('Waktu pelaksanaannya berbeda', $this->galat());

        $this->usulanRekan->update(['tanggal_selesai' => '2026-10-26', 'lokasi' => 'Bandung']);
        $this->pakai(['nomor_acuan' => $acuan->nomor_laporan])->assertSessionHasErrors('nomor_acuan');
        $this->assertStringContainsString('Lokasi tugasnya berbeda', $this->galat());

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertDontSee('data-calon-acuan', false);

        $this->assertNull(LaporanPerjadin::untukUsulan($this->usulan)->tentang);

        // Surat tugas dan lokasi yang hanya berbeda huruf besar dan spasinya tetap dianggap sama.
        $this->usulanRekan->update(['lokasi' => ' jakarta ']);
        $this->pakai(['pilih_acuan' => $acuan->nomor_laporan])->assertSessionHasNoErrors();
        $this->assertSame($acuan->id, LaporanPerjadin::untukUsulan($this->usulan)->id_laporan_acuan);
    }

    public function test_nomor_yang_tidak_ada_milik_sendiri_atau_belum_lengkap_ditolak(): void
    {
        $milikSendiri = LaporanPerjadin::untukUsulan($this->usulan);
        $belumLengkap = LaporanPerjadin::untukUsulan($this->usulanRekan);

        $this->pakai(['nomor_acuan' => 'LPD-2026-0999'])->assertSessionHasErrors('nomor_acuan');
        $this->assertStringContainsString('tidak ditemukan', $this->galat());

        $this->pakai(['nomor_acuan' => $milikSendiri->nomor_laporan])->assertSessionHasErrors('nomor_acuan');
        $this->assertStringContainsString('nomor laporan ini sendiri', $this->galat());

        $this->pakai(['nomor_acuan' => $belumLengkap->nomor_laporan])->assertSessionHasErrors('nomor_acuan');
        $this->assertStringContainsString('belum lengkap diisi pelaksananya', $this->galat());

        $this->pakai([])->assertSessionHasErrors('nomor_acuan');
    }

    public function test_laporan_yang_sudah_dikirim_tidak_dapat_diganti(): void
    {
        $acuan = $this->laporanRekanLengkap();
        LaporanPerjadin::untukUsulan($this->usulan)->kirim();

        $this->pakai(['nomor_acuan' => $acuan->nomor_laporan])->assertForbidden();
        $this->assertNull(LaporanPerjadin::untukUsulan($this->usulan)->id_laporan_acuan);
    }

    public function test_pelaksana_lain_tidak_dapat_memakaikan_laporan(): void
    {
        $acuan = $this->laporanRekanLengkap();

        $this->actingAs($this->rekan)
            ->put(route('dokumen.laporan.pakai', $this->usulan), ['nomor_acuan' => $acuan->nomor_laporan])
            ->assertForbidden();
    }
}
