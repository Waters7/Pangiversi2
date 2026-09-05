<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Rekap Daftar Nominatif untuk satu pegawai, diunduh dari menu Laporan.
 */
class RekapPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    private User $pegawaiLain;

    protected function setUp(): void
    {
        parent::setUp();

        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Keperawatan']);

        $this->pegawai = User::factory()->create([
            'nama' => 'Sandra Tombokan',
            'jabatan' => 'Dosen Lektor Kepala',
            'id_unit' => $unit->id,
        ]);

        $this->pegawaiLain = User::factory()->create(['nama' => 'Merlin Lapananda']);

        $this->perjadin($this->pegawai, 'Jakarta', '2026-01-13');
        $this->perjadin($this->pegawai, 'Surabaya', '2026-02-10');
        $this->perjadin($this->pegawaiLain, 'Denpasar', '2026-01-20');
    }

    private function perjadin(User $pemilik, string $lokasi, string $mulai): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $pemilik->id,
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => $lokasi,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $mulai,
        ]);

        Keuangan::factory()->create(['id_usulan' => $usulan->id, 'total' => 2_500_000]);

        return $usulan;
    }

    private function isiSheet(TestResponse $response): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'uji-rekap-');
        file_put_contents($berkas, $response->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas) === true);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($berkas);

        return $xml;
    }

    private function unduh(array $parameter = []): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan.export-excel', $parameter))
            ->assertOk();
    }

    // ── Isi rekap ──

    public function test_rekap_hanya_memuat_perjadin_pegawai_terpilih(): void
    {
        $xml = $this->isiSheet($this->unduh(['pegawai' => $this->pegawai->id]));

        $this->assertStringContainsString('Jakarta', $xml);
        $this->assertStringContainsString('Surabaya', $xml);
        $this->assertStringNotContainsString('Denpasar', $xml);
    }

    public function test_judul_berubah_menjadi_rekap_per_pegawai(): void
    {
        $xml = $this->isiSheet($this->unduh(['pegawai' => $this->pegawai->id]));

        $this->assertStringContainsString('REKAP PERJALANAN DINAS PER PEGAWAI', $xml);
        $this->assertStringNotContainsString('DAFTAR NOMINATIF PERJALANAN DINAS PEGAWAI', $xml);
    }

    public function test_identitas_pegawai_ditulis_di_atas_tabel(): void
    {
        $xml = $this->isiSheet($this->unduh(['pegawai' => $this->pegawai->id]));

        $this->assertStringContainsString('Sandra Tombokan', $xml);
        $this->assertStringContainsString($this->pegawai->nip, $xml);
        $this->assertStringContainsString('Jurusan Keperawatan', $xml);
        $this->assertStringContainsString('Dosen Lektor Kepala', $xml);
    }

    public function test_kepala_tabel_tetap_lengkap_meski_ada_blok_identitas(): void
    {
        $xml = $this->isiSheet($this->unduh(['pegawai' => $this->pegawai->id]));

        foreach (['NAMA / GOL', 'LAMANYA', 'UANG HARIAN/SAKU', 'UANG PENGINAPAN /', 'T O T A L'] as $judul) {
            $this->assertStringContainsString($judul, $xml);
        }
    }

    public function test_rekap_dapat_disaring_per_bulan(): void
    {
        $xml = $this->isiSheet($this->unduh([
            'pegawai' => $this->pegawai->id,
            'bulan' => '2026-02',
        ]));

        $this->assertStringContainsString('Surabaya', $xml);
        $this->assertStringNotContainsString('Jakarta', $xml);
        $this->assertStringContainsString('Februari 2026', $xml);
    }

    public function test_nama_berkas_menyebut_pegawainya(): void
    {
        $this->unduh(['pegawai' => $this->pegawai->id])
            ->assertDownload('Daftar-Nominatif-sandra-tombokan-Seluruh-Periode.xlsx');
    }

    public function test_tanpa_pemilihan_pegawai_tetap_daftar_nominatif_biasa(): void
    {
        $xml = $this->isiSheet($this->unduh());

        $this->assertStringContainsString('DAFTAR NOMINATIF PERJALANAN DINAS PEGAWAI', $xml);
        $this->assertStringContainsString('Denpasar', $xml);
    }

    // ── Tampilan di menu Laporan ──

    public function test_pemilih_pegawai_tersedia_pada_penyaring(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan'))
            ->assertOk()
            ->assertSee('Semua Pegawai')
            ->assertSee('Sandra Tombokan')
            ->assertSee('Merlin Lapananda');
    }

    public function test_daftar_disaring_saat_pegawai_dipilih(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan', ['pegawai' => $this->pegawai->id]))
            ->assertOk()
            ->assertSee('Ekspor Daftar Nominatif')
            ->assertSee('pegawai terpilih saja')
            ->assertViewHas('usulan', fn ($daftar) => $daftar->total() === 2);
    }

    public function test_pegawai_tanpa_perjadin_tidak_muncul_di_pilihan(): void
    {
        $tanpaPerjadin = User::factory()->create(['nama' => 'Belum Pernah Berangkat']);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan'))
            ->assertOk()
            ->assertViewHas('daftarPegawai', fn ($daftar) => ! $daftar->contains('id', $tanpaPerjadin->id));
    }
}
