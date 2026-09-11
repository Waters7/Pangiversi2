<?php

namespace Tests\Feature;

use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use App\Services\FormatLaporanPerjadin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Laporan perjalanan dinas disusun langsung di aplikasi.
 *
 * Sebelumnya isinya tidak pernah terbaca sistem — pelaksana mengunduh format
 * kosong, mengetiknya di luar, lalu mengunggah hasilnya sebagai berkas mati.
 * Kini kegiatan dan tindak lanjutnya tersimpan sebagai data.
 */
class LaporanPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        User::factory()->create([
            'role' => User::ROLE_PIMPINAN,
            'nama' => 'Dr. Hanung Prasetya',
            'jabatan' => 'Direktur',
        ]);
    }

    /**
     * Tanggal hari ke-$ke perjalanan, sebagai kunci baris uraian kegiatan.
     */
    private function hari(int $ke): string
    {
        return today()->subDays(5 - $ke)->toDateString();
    }

    /**
     * @param  array<string, mixed>  $ubahan
     */
    private function isiLaporan(array $ubahan = []): TestResponse
    {
        return $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.update', $this->usulan), array_merge([
                'id_status_hasil' => StatusHasil::first()->id,
                // Uraian dikunci tanggal: satu baris per hari perjalanan.
                'kegiatan' => [
                    $this->hari(0) => 'Mengikuti rapat koordinasi program.',
                    $this->hari(1) => 'Meninjau pelaksanaan di lapangan.',
                ],
                'tindak_lanjut' => [[
                    'uraian' => 'Menyusun draf pedoman internal.',
                    'penanggung_jawab' => 'Subbag Umum',
                    'target_selesai' => today()->addMonth()->toDateString(),
                    'status' => StatusTindakLanjut::Rencana->value,
                ]],
            ], $ubahan));
    }

    private function selesaikan(): TestResponse
    {
        return $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.selesaikan', $this->usulan));
    }

    // ── Pengisian ──

    public function test_formulir_laporan_terbuka_bagi_pelaksana(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('Uraian Kegiatan')
            ->assertSee('Rencana Tindak Lanjut');
    }

    public function test_laporan_orang_lain_tidak_dapat_dibuka(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertForbidden();
    }

    public function test_kegiatan_dan_tindak_lanjut_tersimpan_berurutan(): void
    {
        $this->isiLaporan();

        $laporan = LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id);

        $this->assertSame([1, 2], $laporan->kegiatan->pluck('urutan')->all());
        $this->assertSame($this->hari(0), $laporan->kegiatan->first()->tanggal->toDateString());
        $this->assertSame('Mengikuti rapat koordinasi program.', $laporan->kegiatan->first()->uraian);
        $this->assertSame('Subbag Umum', $laporan->tindakLanjut->first()->penanggung_jawab);
    }

    public function test_baris_kosong_tidak_ikut_tersimpan(): void
    {
        $this->isiLaporan(['kegiatan' => [
            $this->hari(0) => 'Kegiatan pertama.',
            $this->hari(1) => '',
            $this->hari(2) => '   ',
        ]]);

        $this->assertSame(1, LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->kegiatan()->count());
    }

    /**
     * Baris ditulis ulang seluruhnya saat disimpan, sehingga menghapus satu
     * baris di layar benar-benar menghapusnya — bukan menyisakan baris lama.
     */
    public function test_menyimpan_ulang_mengganti_baris_lama(): void
    {
        $this->isiLaporan();
        $this->isiLaporan(['kegiatan' => [$this->hari(0) => 'Hanya satu kegiatan.']]);

        $laporan = LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id);

        $this->assertSame(1, $laporan->kegiatan()->count());
        $this->assertSame('Hanya satu kegiatan.', $laporan->kegiatan()->first()->uraian);
    }

    // ── Menyelesaikan laporan ──

    public function test_laporan_kosong_tidak_dapat_diselesaikan(): void
    {
        $this->selesaikan()->assertSessionHas('error');

        $this->assertNull(LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)?->diselesaikan_at);
    }

    public function test_laporan_terisi_dapat_diselesaikan(): void
    {
        $this->isiLaporan();

        $this->selesaikan()->assertSessionHas('success');

        $this->assertNotNull(LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->diselesaikan_at);
    }

    public function test_dokumen_terbit_setelah_laporan_selesai(): void
    {
        $this->isiLaporan();
        $this->selesaikan();

        $balasan = $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.cetak', $this->usulan))
            ->assertOk();

        $this->assertSame('application/pdf', $balasan->headers->get('content-type'));
    }

    public function test_dokumen_belum_dapat_dicetak_sebelum_diselesaikan(): void
    {
        $this->isiLaporan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.cetak', $this->usulan))
            ->assertForbidden();
    }

    public function test_laporan_dapat_dibuka_kembali_untuk_diperbaiki(): void
    {
        $this->isiLaporan();
        $this->selesaikan();

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.buka', $this->usulan))
            ->assertSessionHas('success');

        $this->assertNull(LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->diselesaikan_at);
    }

    public function test_menu_dokumen_menandai_laporan_yang_sudah_selesai(): void
    {
        $this->isiLaporan();
        $this->selesaikan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertSee('Unduh Dokumen Laporan');
    }

    // ── Daftar tindak lanjut ──

    public function test_submenu_daftar_tindak_lanjut_tersedia(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee('Daftar Tindak Lanjut')
            ->assertSee(route('dokumen.tindak-lanjut'), false);
    }

    public function test_tindak_lanjut_muncul_pada_daftarnya(): void
    {
        $this->isiLaporan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.tindak-lanjut'))
            ->assertOk()
            ->assertSee('Menyusun draf pedoman internal.')
            ->assertSee($this->usulan->no_usulan);
    }

    public function test_tindak_lanjut_pegawai_lain_tidak_ikut_terbaca(): void
    {
        $this->isiLaporan();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('dokumen.tindak-lanjut'))
            ->assertOk()
            ->assertDontSee('Menyusun draf pedoman internal.');
    }

    public function test_status_tindak_lanjut_dapat_diperbarui_dari_daftarnya(): void
    {
        $this->isiLaporan();

        $tindak = LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->tindakLanjut->first();

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.tindak-lanjut.status', $tindak), [
                'status' => StatusTindakLanjut::Selesai->value,
            ])
            ->assertSessionHas('success');

        $this->assertSame(StatusTindakLanjut::Selesai, $tindak->fresh()->status);
    }

    public function test_status_tindak_lanjut_orang_lain_tidak_dapat_diubah(): void
    {
        $this->isiLaporan();

        $tindak = LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->tindakLanjut->first();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->put(route('dokumen.tindak-lanjut.status', $tindak), [
                'status' => StatusTindakLanjut::Selesai->value,
            ])
            ->assertForbidden();
    }

    public function test_daftar_dapat_disaring_menurut_status(): void
    {
        $this->isiLaporan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.tindak-lanjut', ['status' => StatusTindakLanjut::Selesai->value]))
            ->assertOk()
            ->assertDontSee('Menyusun draf pedoman internal.');
    }

    public function test_tindak_lanjut_terlambat_ditandai(): void
    {
        $this->isiLaporan(['tindak_lanjut' => [[
            'uraian' => 'Tindak lanjut yang lewat tenggat.',
            'target_selesai' => today()->subWeek()->toDateString(),
            'status' => StatusTindakLanjut::Rencana->value,
        ]]]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.tindak-lanjut'))
            ->assertOk()
            ->assertSee('terlambat');
    }

    // ── Kelengkapan berkas ──

    public function test_laporan_yang_belum_selesai_masih_ditagih(): void
    {
        $this->isiLaporan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertViewHas('berkasKurang', fn (array $kurang) => in_array('Laporan perjalanan dinas', $kurang, true));
    }

    public function test_laporan_selesai_tidak_lagi_ditagih(): void
    {
        $this->isiLaporan();
        $this->selesaikan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan))
            ->assertOk()
            ->assertViewHas('berkasKurang', fn (array $kurang) => ! in_array('Laporan perjalanan dinas', $kurang, true));
    }
    // ── Tempat kegiatan per hari ──

    public function test_tempat_kegiatan_tersimpan_per_hari(): void
    {
        $this->isiLaporan([
            'tempat' => [
                $this->hari(0) => 'Kantor Dinas Kesehatan Provinsi',
                $this->hari(1) => 'Puskesmas Tuminting',
            ],
        ]);

        $tempat = LaporanPerjadin::firstOrFail()->kegiatan->pluck('tempat', 'urutan');

        $this->assertSame('Kantor Dinas Kesehatan Provinsi', $tempat[1]);
        $this->assertSame('Puskesmas Tuminting', $tempat[2]);
    }

    public function test_formulir_menawarkan_kolom_tempat_tiap_hari(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('name="tempat['.$this->hari(0).']"', false)
            ->assertSee('Tempat kegiatan');
    }

    /** Hari yang belum diisi tempatnya diawali dari instansi dan lokasi usulan. */
    public function test_tempat_bawaan_dari_usulan(): void
    {
        $this->usulan->update(['instansi' => 'Kemenkes RI', 'lokasi' => 'Jakarta']);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('value="Kemenkes RI — Jakarta"', false);
    }

    public function test_tempat_tercetak_pada_tiap_baris_dokumen(): void
    {
        $this->isiLaporan([
            'tempat' => [
                $this->hari(0) => 'Kantor Dinas Kesehatan Provinsi',
                $this->hari(1) => 'Puskesmas Tuminting',
            ],
        ]);

        $laporan = LaporanPerjadin::firstOrFail()->load('kegiatan', 'tindakLanjut', 'pimpinan');
        $html = view('dokumen.laporan-cetak', app(FormatLaporanPerjadin::class)->data($this->usulan->fresh()) + [
            'laporan' => $laporan,
            'qrPelaksana' => null,
            'qrPimpinan' => null,
        ])->render();

        $this->assertStringContainsString('Kantor Dinas Kesehatan Provinsi', $html);
        $this->assertStringContainsString('Puskesmas Tuminting', $html);
    }
}
