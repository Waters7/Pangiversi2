<?php

namespace Tests\Feature;

use App\Enums\StatusTindakLanjut;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Daftar laporan perjalanan dinas, uraian kegiatan per hari, dasar
 * pelaksanaan yang diturunkan sendiri, dan status hasil dari master data.
 */
class DaftarLaporanPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        // Tiga hari perjalanan: 5, 4, dan 3 hari lalu.
        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.01.02/F.XXX/1557/2026',
            'uraian' => 'Konsultasi teknis sistem informasi.',
            'lokasi' => 'Jakarta',
            'tanggal_mulai' => today()->subDays(5)->toDateString(),
            'tanggal_selesai' => today()->subDays(3)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
    }

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
                'id_status_hasil' => StatusHasil::firstWhere('nama', 'Selesai dikerjakan')->id,
                'kegiatan' => [$this->hari(0) => 'Rapat pembukaan.'],
                'tindak_lanjut' => [[
                    'uraian' => 'Menyusun laporan internal.',
                    'status' => StatusTindakLanjut::Rencana->value,
                ]],
            ], $ubahan));
    }

    // ── Submenu daftar laporan ──

    public function test_submenu_list_laporan_tersedia(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee('List Laporan Perjadin')
            ->assertSee(route('dokumen.laporan.index'), false);
    }

    /**
     * Perjalanan yang laporannya belum pernah dibuka pun harus terlihat —
     * justru itu yang perlu dikerjakan.
     */
    public function test_perjalanan_tanpa_laporan_tetap_terdaftar(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('Belum Selesai');
    }

    public function test_daftar_menandai_laporan_yang_sudah_selesai(): void
    {
        $this->isiLaporan();
        $this->actingAs($this->pelaksana)->put(route('dokumen.laporan.selesaikan', $this->usulan));

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertSee('Selesai dikerjakan');
    }

    public function test_daftar_menawarkan_aksi_lihat_dan_edit(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertSee(route('dokumen.laporan.show', $this->usulan->no_usulan), false)
            ->assertSee(route('dokumen.laporan.edit', $this->usulan->no_usulan), false);
    }

    public function test_laporan_pegawai_lain_tidak_ikut_terdaftar(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertDontSee($this->usulan->no_usulan);
    }

    // ── Terkunci setelah perjalanan ditutup ──

    public function test_aksi_edit_hilang_setelah_perjalanan_ditutup(): void
    {
        $this->usulan->update(['status' => StatusUsulan::Selesai->value]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.index'))
            ->assertOk()
            ->assertSee('Terkunci')
            // Dibandingkan sebagai atribut utuh: alamat ubah adalah awalan
            // dari alamat lihat, jadi pencocokan sepotong akan salah kena.
            ->assertDontSee('href="'.route('dokumen.laporan.edit', $this->usulan->no_usulan).'"', false);
    }

    /**
     * Dijaga di penyimpanan, bukan hanya dengan menyembunyikan tombolnya:
     * tanpa ini laporan masih bisa diubah lewat kiriman langsung.
     */
    public function test_penyimpanan_ditolak_setelah_perjalanan_ditutup(): void
    {
        $this->usulan->update(['status' => StatusUsulan::Selesai->value]);

        $this->isiLaporan()->assertForbidden();

        $this->assertNull(LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)?->id_status_hasil);
    }

    // ── Halaman lihat ──

    public function test_halaman_lihat_menampilkan_isi_laporan(): void
    {
        $this->isiLaporan();

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Rapat pembukaan.')
            ->assertSee('Menyusun laporan internal.')
            ->assertSee('Selesai dikerjakan');
    }

    public function test_halaman_lihat_orang_lain_ditolak(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('dokumen.laporan.show', $this->usulan))
            ->assertForbidden();
    }

    // ── Uraian kegiatan per hari ──

    public function test_formulir_menyediakan_satu_baris_per_hari_perjalanan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertViewHas('hariPerjalanan', fn (array $hari) => count($hari) === 3)
            ->assertSee('kegiatan['.$this->hari(0).']', false)
            ->assertSee('kegiatan['.$this->hari(2).']', false);
    }

    public function test_uraian_tersimpan_beserta_tanggalnya(): void
    {
        $this->isiLaporan(['kegiatan' => [
            $this->hari(0) => 'Hari pertama.',
            $this->hari(2) => 'Hari ketiga.',
        ]]);

        $kegiatan = LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->kegiatan;

        $this->assertCount(2, $kegiatan);
        $this->assertSame($this->hari(0), $kegiatan->first()->tanggal->toDateString());
        $this->assertSame($this->hari(2), $kegiatan->last()->tanggal->toDateString());
    }

    /**
     * Kuncinya tanggal, bukan urutan baris: hari yang dikosongkan tidak
     * menggeser uraian hari-hari sesudahnya.
     */
    public function test_hari_yang_dikosongkan_tidak_menggeser_hari_lain(): void
    {
        $this->isiLaporan(['kegiatan' => [
            $this->hari(0) => '',
            $this->hari(1) => 'Hari kedua.',
        ]]);

        $kegiatan = LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->kegiatan;

        $this->assertCount(1, $kegiatan);
        $this->assertSame($this->hari(1), $kegiatan->first()->tanggal->toDateString());
    }

    public function test_perjalanan_sehari_tetap_punya_satu_baris(): void
    {
        $this->usulan->update([
            'tanggal_mulai' => today()->subDay()->toDateString(),
            'tanggal_selesai' => today()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertViewHas('hariPerjalanan', fn (array $hari) => count($hari) === 1);
    }

    // ── Dasar pelaksanaan ──

    public function test_dasar_pelaksanaan_tidak_lagi_diminta_ke_pelaksana(): void
    {
        $isi = $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="dasar"', $isi);
    }

    public function test_dasar_pelaksanaan_diturunkan_dari_surat_tugas_dan_maksud(): void
    {
        $laporan = LaporanPerjadin::firstOrCreate(['id_usulan' => $this->usulan->id]);

        $dasar = $laporan->dasarPelaksanaan();

        $this->assertStringContainsString('KP.01.02/F.XXX/1557/2026', $dasar);
        $this->assertStringContainsString('Konsultasi teknis sistem informasi.', $dasar);
    }

    public function test_dasar_pelaksanaan_tampil_pada_formulir(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->assertSee('Surat Tugas Nomor KP.01.02/F.XXX/1557/2026');
    }

    // ── Status hasil ──

    public function test_tiga_status_baku_tersedia(): void
    {
        $this->assertSame(
            ['Selesai dikerjakan', 'Perlu tindak lanjut', 'Tidak selesai'],
            StatusHasil::pilihan()->pluck('nama')->all()
        );
    }

    public function test_formulir_menawarkan_status_hasil_sebagai_pilihan(): void
    {
        $isi = $this->actingAs($this->pelaksana)
            ->get(route('dokumen.laporan.edit', $this->usulan))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="id_status_hasil"', $isi);
        $this->assertStringContainsString('Perlu tindak lanjut', $isi);
        // Hasil tidak lagi diketik bebas.
        $this->assertStringNotContainsString('name="hasil"', $isi);
    }

    public function test_status_hasil_tersimpan_pada_laporan(): void
    {
        $status = StatusHasil::firstWhere('nama', 'Tidak selesai');

        $this->isiLaporan(['id_status_hasil' => $status->id]);

        $this->assertSame(
            $status->id,
            LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->id_status_hasil
        );
    }

    public function test_laporan_tanpa_status_hasil_belum_dapat_diselesaikan(): void
    {
        $this->isiLaporan(['id_status_hasil' => null]);

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.selesaikan', $this->usulan))
            ->assertSessionHas('error');

        $this->assertNull(LaporanPerjadin::firstWhere('id_usulan', $this->usulan->id)->diselesaikan_at);
    }

    public function test_status_nonaktif_tidak_lagi_ditawarkan(): void
    {
        StatusHasil::firstWhere('nama', 'Tidak selesai')->update(['is_aktif' => false]);

        $this->assertFalse(StatusHasil::pilihan()->contains('nama', 'Tidak selesai'));
    }
}
