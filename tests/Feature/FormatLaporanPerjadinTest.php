<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Kegiatan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\FormatLaporanPerjadin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Format baku Laporan Perjalanan Dinas dapat diunduh dari menu Dokumen
 * sebelum pegawai mengunggah laporannya kembali.
 */
class FormatLaporanPerjadinTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create([
            'role' => PeranPengguna::DosenTendik->value,
            'nama' => 'Rahmatullah Ade, S.Sos',
        ]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KM.06.02/F.XXX/1557/2026',
            'lokasi' => 'Jakarta',
            'instansi' => 'Poltekkes Kemenkes Jakarta I',
            'uraian' => 'Mengikuti Sertifikasi Pustakawan Kemenkes',
            'tanggal_mulai' => '2026-06-23',
            'tanggal_selesai' => '2026-06-24',
            'id_kegiatan' => Kegiatan::factory()->create(['nama' => 'Mengikuti diklat'])->id,
        ]);
    }

    private function unduh(): TestResponse
    {
        return $this->actingAs($this->pelaksana)
            ->get(route('dokumen.format-laporan', $this->usulan->no_usulan));
    }

    // ── Berkasnya ──

    public function test_format_dapat_diunduh_sebagai_pdf(): void
    {
        $this->unduh()
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("Format-Laporan-Perjadin_{$this->usulan->no_usulan}.pdf");
    }

    public function test_halaman_memakai_judul_resmi(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('LAPORAN PERJALANAN DINAS', $html);
        $this->assertStringContainsString('PEGAWAI POLTEKKES KEMENKES MANADO', $html);
    }

    public function test_identitas_dan_surat_tugas_sudah_terisi(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('KM.06.02/F.XXX/1557/2026', $html);
        $this->assertStringContainsString('Rahmatullah Ade, S.Sos', $html);
        $this->assertStringContainsString($this->pelaksana->nip, $html);
        $this->assertStringContainsString('Mengikuti Sertifikasi Pustakawan Kemenkes', $html);
    }

    public function test_tanggal_perjalanan_ditulis_sebagai_rentang(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('23–24 Juni 2026', $html);
        $this->assertStringContainsString('Selasa s/d Rabu, 23 s/d 24 Juni 2026', $html);
    }

    public function test_kolom_kegiatan_dibiarkan_kosong_untuk_diisi(): void
    {
        $html = $this->render();

        foreach (['NO', 'TEMPAT KEGIATAN', 'HARI, TANGGAL', 'URAIAN KEGIATAN', 'RENCANA TINDAK LANJUT'] as $kolom) {
            $this->assertStringContainsString($kolom, $html);
        }

        $this->assertStringContainsString('Petunjuk pengisian', $html);
    }

    public function test_blok_tanda_tangan_memuat_direktur(): void
    {
        $direktur = User::factory()->create([
            'role' => PeranPengguna::Pimpinan->value,
            'jabatan' => 'Direktur',
            'nama' => 'Dr. Hanung Prasetya, S.Kp., M.Si',
        ]);

        $html = $this->render();

        $this->assertStringContainsString('Mengetahui,', $html);
        $this->assertStringContainsString('Direktur Poltekkes Kemenkes Manado', $html);
        $this->assertStringContainsString($direktur->nama, $html);
        $this->assertStringContainsString('Yang membuat', $html);
    }

    public function test_wakil_direktur_tidak_menggantikan_direktur(): void
    {
        User::factory()->create([
            'role' => PeranPengguna::Pimpinan->value,
            'jabatan' => 'Wakil Direktur I',
            'nama' => 'Semuel Tambuwun',
        ]);
        $direktur = User::factory()->create([
            'role' => PeranPengguna::Pimpinan->value,
            'jabatan' => 'Direktur',
            'nama' => 'Hanung Prasetya',
        ]);

        $html = $this->render();

        $this->assertStringContainsString($direktur->nama, $html);
        $this->assertStringNotContainsString('Semuel Tambuwun', $html);
    }

    // ── Akses ──

    public function test_pegawai_lain_tidak_dapat_mengunduh(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dokumen.format-laporan', $this->usulan->no_usulan))
            ->assertForbidden();
    }

    /**
     * Laporan kini disusun di aplikasi, jadi menu Dokumen menawarkan
     * pengisiannya — bukan lagi mengunduh format kosong untuk diketik
     * di luar lalu diunggah kembali.
     */
    public function test_menu_dokumen_menawarkan_pengisian_laporan(): void
    {
        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $this->usulan->no_usulan))
            ->assertOk()
            ->assertSee('Isi Laporan')
            ->assertSee(route('dokumen.laporan.edit', $this->usulan->no_usulan), false);
    }

    /**
     * Render templatnya memakai data yang sama persis dengan controller,
     * agar isinya dapat diperiksa tanpa membongkar berkas PDF.
     */
    private function render(): string
    {
        $data = app(FormatLaporanPerjadin::class)->data($this->usulan->fresh());

        return view('dokumen.format-laporan', $data)->render();
    }
}
