<?php

namespace Tests\Feature;

use App\Enums\BagianLaporan;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
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
 * Simpan Draf tidak boleh menghilangkan isian yang sudah diketik pelaksana:
 * unggahan yang ditolak tidak ikut membatalkan uraiannya, isian yang tertolak
 * kembali utuh ke formulir, dan kiriman yang gagal dapat dipulihkan.
 */
class SimpanDrafLaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.01.02/F.XXX/1557/2026',
            'uraian' => 'Rapat Koordinasi Nasional',
            'tanggal_mulai' => today()->subDays(3)->toDateString(),
            'tanggal_selesai' => today()->subDays(2)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
        User::factory()->create(['role' => User::ROLE_PIMPINAN, 'jabatan' => 'Direktur']);
    }

    /** @param  array<string, mixed>  $isian */
    private function simpan(array $isian): TestResponse
    {
        return $this->actingAs($this->pelaksana)
            ->from(route('dokumen.laporan.edit', $this->usulan))
            ->put(route('dokumen.laporan.update', $this->usulan), $isian);
    }

    /** @return array<string, mixed> */
    private function isianLengkap(): array
    {
        return [
            'tentang' => 'Rapat Koordinasi Nasional',
            'id_status_hasil' => StatusHasil::first()->id,
            'bagian' => ['kegiatan' => 'Uraian kegiatan', 'hasil' => 'Uraian hasil', 'simpulan' => 'Uraian simpulan'],
            'tindak_lanjut' => [['uraian' => 'Menyusun pedoman', 'status' => 'rencana']],
        ];
    }

    private function laporan(): LaporanPerjadin
    {
        return LaporanPerjadin::with('dokumentasi', 'tindakLanjut')->firstWhere('id_usulan', $this->usulan->id);
    }

    public function test_foto_yang_ditolak_tidak_membatalkan_isian_dan_foto_lain(): void
    {
        $this->simpan($this->isianLengkap() + ['action' => 'draft', 'dokumentasi' => [
            'kegiatan' => UploadedFile::fake()->image('dari-ponsel.jpg')->size(3000),
            'hasil' => UploadedFile::fake()->image('kecil.jpg')->size(500),
        ]])
            ->assertRedirect(route('dokumen.laporan.edit', $this->usulan))
            ->assertSessionHasErrors('dokumentasi.kegiatan')
            ->assertSessionHas('error', 'Isi laporan sudah tersimpan, tetapi unggahan berikut ditolak. Perbaiki lalu unggah lagi.')
            ->assertSessionHas('isian_tersimpan', true);

        $laporan = $this->laporan();

        $this->assertSame('Uraian kegiatan', $laporan->kegiatan_dilaksanakan);
        $this->assertSame('Menyusun pedoman', $laporan->tindakLanjut->sole()->uraian);
        $this->assertNull($laporan->dokumentasiBagian(BagianLaporan::Kegiatan));
        $this->assertNotNull($laporan->dokumentasiBagian(BagianLaporan::Hasil));
    }

    public function test_foto_lama_bertahan_saat_penggantinya_ditolak(): void
    {
        $this->simpan(['dokumentasi' => ['hasil' => UploadedFile::fake()->image('lama.jpg')]])->assertSessionHasNoErrors();
        $lama = $this->laporan()->dokumentasiBagian(BagianLaporan::Hasil)->berkas;

        $this->simpan([
            'dokumentasi' => ['hasil' => UploadedFile::fake()->image('baru.jpg')->size(3000)],
            'hapus_dokumentasi' => ['hasil' => '1'],
        ])->assertSessionHasErrors('dokumentasi.hasil');

        $this->assertSame($lama, $this->laporan()->dokumentasiBagian(BagianLaporan::Hasil)->berkas);
        Storage::disk('public')->assertExists($lama);
    }

    public function test_laporan_berunggahan_ditolak_tidak_diteruskan_ke_pimpinan(): void
    {
        $this->simpan($this->isianLengkap() + [
            'action' => 'kirim',
            'dokumentasi' => ['hasil' => UploadedFile::fake()->image('besar.jpg')->size(3000)],
        ])->assertSessionHasErrors('dokumentasi.hasil');

        $laporan = $this->laporan();

        $this->assertSame('Uraian hasil', $laporan->hasil);
        $this->assertNull($laporan->dikirim_at);
        $this->assertNull($laporan->diselesaikan_at);
    }

    public function test_berkas_ditolak_tidak_membatalkan_isian_laporan_format_lama(): void
    {
        LaporanPerjadin::create(['id_usulan' => $this->usulan->id, 'versi_format' => LaporanPerjadin::FORMAT_LAMA]);

        $this->simpan([
            'kesimpulan' => 'Kesimpulan format lama',
            'kegiatan' => [today()->subDays(3)->toDateString() => 'Uraian hari pertama'],
            'berkas_laporan' => UploadedFile::fake()->create('laporan.exe', 100),
        ])->assertSessionHasErrors('berkas_laporan');

        $laporan = $this->laporan();

        $this->assertSame('Kesimpulan format lama', $laporan->kesimpulan);
        $this->assertSame('Uraian hari pertama', $laporan->kegiatan()->sole()->uraian);
        $this->assertNull($laporan->berkas_laporan);
    }

    public function test_uraian_panjang_tersimpan_dan_yang_melampaui_batas_kembali_utuh_ke_formulir(): void
    {
        $this->simpan(['bagian' => ['kegiatan' => str_repeat('a', 30_000)]])->assertSessionHasNoErrors();
        $this->assertSame(30_000, mb_strlen($this->laporan()->kegiatan_dilaksanakan));

        $this->simpan([
            'bagian' => ['kegiatan' => str_repeat('b', 30_001), 'hasil' => 'Hasil yang sudah diketik'],
            'tindak_lanjut' => [['uraian' => 'Tindak lanjut yang sudah diketik', 'status' => 'rencana']],
        ])->assertSessionHasErrors(['bagian.kegiatan' => 'Uraian satu bagian paling panjang 30000 karakter.']);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertSee('Hasil yang sudah diketik')
            ->assertSee('Tindak lanjut yang sudah diketik');
    }

    /** Uraian usulan tidak berbatas, sedangkan formulir mengisikannya sebagai judul. */
    public function test_judul_dari_uraian_usulan_yang_panjang_tidak_menggagalkan_penyimpanan(): void
    {
        $this->usulan->update(['uraian' => str_repeat('Kegiatan panjang ', 40)]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertViewHas('awal', fn (array $awal) => mb_strlen($awal['tentang']) === 500);
    }

    public function test_draf_peramban_terpasang_selama_laporan_masih_dapat_disunting(): void
    {
        $halaman = $this->actingAs($this->pelaksana)->get(route('dokumen.laporan.edit', $this->usulan));
        $laporan = $this->laporan();

        $halaman->assertSee('pangi-draf-laporan-'.$laporan->nomor_laporan, false)
            ->assertSee('versi: '.$laporan->updated_at->getTimestamp(), false)
            ->assertSee('data-pesan-kirim', false);

        $laporan->update(['diselesaikan_at' => now()]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertDontSee('pangi-draf-laporan-', false)
            ->assertDontSee('data-pesan-kirim', false);
    }

    /** Draf peramban baru dibuang setelah server benar-benar menerima isiannya. */
    public function test_draf_peramban_dilepas_setelah_isian_diterima_server(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertSee('tersimpan: false', false);

        $this->simpan(['bagian' => ['kegiatan' => 'Uraian']])->assertSessionHas('isian_tersimpan', true);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertSee('tersimpan: true', false);
    }

    /** Kirimannya terbuang, jadi pengguna harus kembali ke formulir untuk memulihkan drafnya. */
    public function test_sesi_habis_saat_menyimpan_kembali_ke_formulir_setelah_masuk(): void
    {
        $formulir = route('dokumen.laporan.edit', $this->usulan);

        $this->from($formulir)
            ->put(route('dokumen.laporan.update', $this->usulan), ['bagian' => ['kegiatan' => 'Uraian']])
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', $formulir);
    }
}
