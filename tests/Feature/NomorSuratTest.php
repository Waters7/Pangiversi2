<?php

namespace Tests\Feature;

use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Register nomor SPD dan surat tugas per bulan, untuk dicocokkan arsiparis
 * dengan buku agenda surat keluar.
 */
class NomorSuratTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Rina Kusumawati']);
    }

    private function spd(string $tanggal, string $nomor, ?string $noTugas = 'KP.01.02/F.XXX/1557/2026'): SuratPerjalananDinas
    {
        $spd = SuratPerjalananDinas::create([
            'id_pembuat' => $this->pelaksana->id,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => $tanggal,
            'maksud' => 'Rapat koordinasi kurikulum.',
            'alat_angkut' => 'Kendaraan Umum',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Bitung',
            'tanggal_berangkat' => $tanggal,
            'tanggal_kembali' => $tanggal,
            'lama_hari' => 1,
            'no_tugas' => $noTugas,
        ]);

        $spd->pelaksana()->create([
            'urutan' => 1,
            'id_user' => $this->pelaksana->id,
            'nomor_surat' => $nomor,
            'nama' => $this->pelaksana->nama,
            'nip' => '198905122014022003',
        ]);

        return $spd;
    }

    private function isiSheet(TestResponse $response): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
        file_put_contents($berkas, $response->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas) === true, 'Berkas xlsx tidak dapat dibuka sebagai arsip.');
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($berkas);

        return $xml;
    }

    public function test_register_memuat_ketiga_nomor_dikelompokkan_per_bulan(): void
    {
        $spd = $this->spd('2026-10-05', 'PJ-BID-2026-10-003');
        Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'id_spd' => $spd->id,
            'status' => 'disetujui',
            'no_spd' => 'KU.02.04/F.XXX.8/2471/2026',
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit-log.nomor-surat', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('Oktober 2026')
            ->assertSee('PJ-BID-2026-10-003')
            ->assertSeeText('KU.02.04/F.XXX.8/2471/2026')
            ->assertSeeText('KP.01.02/F.XXX/1557/2026')
            ->assertSee('Rina Kusumawati');
    }

    /** SPD yang belum dipakai usulan tetap tercantum, ditandai belum bertanda tangan. */
    public function test_spd_tanpa_usulan_ditandai_belum_dicatat(): void
    {
        $this->spd('2026-10-05', 'PJ-BID-2026-10-004');

        $this->actingAs($this->admin)
            ->get(route('audit-log.nomor-surat', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('PJ-BID-2026-10-004')
            ->assertSee('Belum dicatat')
            ->assertSee('Belum dipakai');
    }

    /** Perjalanan tanpa SPD aplikasi (supervisi dalam kota, misalnya) ikut tercantum dengan nomor pada usulannya. */
    public function test_usulan_tanpa_spd_aplikasi_ikut_tercantum_kecuali_draf(): void
    {
        Usulan::factory()->create([
            'status' => 'disetujui',
            'no_tugas' => 'KP.01.02/F.XXX/1284/2026',
            'no_spd' => null,
            'created_at' => '2026-09-14 08:00:00',
        ]);
        Usulan::factory()->create([
            'status' => 'draft',
            'no_tugas' => 'KP.01.02/F.XXX/0001/2026',
            'created_at' => '2026-09-14 08:00:00',
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit-log.nomor-surat', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('September 2026')
            ->assertSeeText('KP.01.02/F.XXX/1284/2026')
            ->assertSee('Tanpa SPD aplikasi')
            ->assertDontSeeText('KP.01.02/F.XXX/0001/2026');
    }

    public function test_saringan_bulan_dan_pencarian_dihormati(): void
    {
        $this->spd('2026-09-20', 'PJ-BID-2026-09-010', 'KP.01.02/F.XXX/1111/2026');
        $this->spd('2026-10-05', 'PJ-BID-2026-10-003', 'KP.01.02/F.XXX/2222/2026');

        $this->actingAs($this->admin)
            ->get(route('audit-log.nomor-surat', ['tahun' => 2026, 'bulan' => 10]))
            ->assertOk()
            ->assertSee('PJ-BID-2026-10-003')
            ->assertDontSee('PJ-BID-2026-09-010');

        $this->actingAs($this->admin)
            ->get(route('audit-log.nomor-surat', ['tahun' => 2026, 'search' => 'F.XXX/1111']))
            ->assertOk()
            ->assertSee('PJ-BID-2026-09-010')
            ->assertDontSee('PJ-BID-2026-10-003');
    }

    public function test_ekspor_excel_memuat_nomor_dan_judul_bulan(): void
    {
        $spd = $this->spd('2026-10-05', 'PJ-BID-2026-10-003');
        Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'id_spd' => $spd->id,
            'status' => 'disetujui',
            'no_spd' => 'KU.02.04/F.XXX.8/2471/2026',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('audit-log.nomor-surat.ekspor', ['tahun' => 2026, 'bulan' => 10]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('Register-Nomor-Surat-Oktober-2026.xlsx', (string) $response->headers->get('content-disposition'));

        $xml = $this->isiSheet($response);
        $this->assertStringContainsString('REGISTER NOMOR SPD DAN SURAT TUGAS', $xml);
        $this->assertStringContainsString('OKTOBER 2026', $xml);
        $this->assertStringContainsString('PJ-BID-2026-10-003', $xml);
        $this->assertStringContainsString('KU.02.04/F.XXX.8/2471/2026', $xml);
        $this->assertStringContainsString('KP.01.02/F.XXX/1557/2026', $xml);
    }

    public function test_tertutup_bagi_peran_tanpa_hak_jejak_audit(): void
    {
        $this->actingAs($this->pelaksana)->get(route('audit-log.nomor-surat'))->assertForbidden();
        $this->actingAs($this->pelaksana)->get(route('audit-log.nomor-surat.ekspor'))->assertForbidden();
    }
}
