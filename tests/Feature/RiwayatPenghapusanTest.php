<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penghapusan usulan perjadin dan SPD direkam beserta cuplikan isinya, dan
 * ditampilkan pada submenu Jejak Audit tersendiri.
 *
 * Setelah dihapus, usulan dan SPD hilang bersama relasinya — cuplikan pada
 * jejak audit adalah satu-satunya sumber untuk menjawab apa yang dihapus.
 */
class RiwayatPenghapusanTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'nama' => 'Rina Kusumawati']);
        $this->admin = User::factory()->administrator()->create();
    }

    public function test_menghapus_usulan_merekam_cuplikan_isinya(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => 'draft',
            'lokasi' => 'Bitung',
            'instansi' => 'RSUD Bitung',
            'tanggal_mulai' => '2026-10-12',
            'tanggal_selesai' => '2026-10-13',
            'no_spd' => 'KU.02.04/F.XXX.8/2471/2026',
        ]);
        $usulan->peserta()->create(['id_user' => $this->pengusul->id, 'nama' => 'Rina Kusumawati', 'peran' => 'ketua']);

        $this->actingAs($this->pengusul)->delete(route('usulan.destroy', $usulan))->assertRedirect();

        $log = AuditLog::where('objek', AuditLog::OBJEK_USULAN)->sole();
        $this->assertSame(AuditLog::AKSI_DIHAPUS, $log->aksi);
        $this->assertSame($usulan->no_usulan, $log->cuplikan['nomor']);
        $this->assertSame(['Rina Kusumawati'], $log->cuplikan['pelaksana']);
        $this->assertSame('Bitung — RSUD Bitung', $log->cuplikan['tujuan']);
        $this->assertSame('2026-10-12', $log->cuplikan['tanggal_mulai']);
        $this->assertSame('KU.02.04/F.XXX.8/2471/2026', $log->cuplikan['rincian']['No. SPD']);
    }

    public function test_menghapus_spd_tercatat_beserta_cuplikannya(): void
    {
        $spd = $this->terbitkanSpd($this->pengusul);
        $nomor = $spd->pelaksana->first()->nomor_surat;

        $this->actingAs($this->pengusul)->delete(route('spd.destroy', $spd))->assertRedirect(route('spd.index'));

        $this->assertDatabaseMissing('surat_perjalanan_dinas', ['id' => $spd->id]);

        $log = AuditLog::where('objek', AuditLog::OBJEK_SPD)->sole();
        $this->assertSame(AuditLog::AKSI_DIHAPUS, $log->aksi);
        $this->assertSame($this->pengusul->id, $log->id_user);
        $this->assertSame($nomor, $log->cuplikan['nomor']);
        $this->assertSame([$this->pengusul->nama], $log->cuplikan['pelaksana']);
        $this->assertSame('Jakarta', $log->cuplikan['tujuan']);
    }

    public function test_halaman_menampilkan_isi_dan_penghapusnya_serta_dapat_disaring(): void
    {
        $this->penghapusan(AuditLog::OBJEK_USULAN, 'PJ-BID-2026-10-002', 'Bitung');
        $this->penghapusan(AuditLog::OBJEK_SPD, 'PJ-BID-2026-10-003', 'Makassar');
        AuditLog::factory()->create(['aksi' => AuditLog::AKSI_PEMBAYARAN, 'deskripsi' => 'Uang muka dibayarkan.']);

        $this->actingAs($this->admin)
            ->get(route('audit-log.penghapusan'))
            ->assertOk()
            ->assertSee('PJ-BID-2026-10-002')
            ->assertSee('PJ-BID-2026-10-003')
            ->assertSee('Bitung')
            ->assertSee($this->pengusul->nama)
            ->assertDontSee('Uang muka dibayarkan.');

        $this->actingAs($this->admin)
            ->get(route('audit-log.penghapusan', ['objek' => AuditLog::OBJEK_SPD]))
            ->assertOk()
            ->assertSee('PJ-BID-2026-10-003')
            ->assertDontSee('PJ-BID-2026-10-002');
    }

    /** Nomor resmi memuat garis miring; pencariannya tetap menemukannya apa adanya. */
    public function test_pencarian_menemukan_nomor_bergaris_miring(): void
    {
        $this->penghapusan(AuditLog::OBJEK_USULAN, 'PJ-BID-2026-10-002', 'Bitung', ['No. SPD' => 'KU.02.04/F.XXX.8/2471/2026']);
        $this->penghapusan(AuditLog::OBJEK_USULAN, 'PJ-BID-2026-10-009', 'Tomohon', ['No. SPD' => 'KU.02.04/F.XXX.8/9999/2026']);

        $this->actingAs($this->admin)
            ->get(route('audit-log.penghapusan', ['search' => 'F.XXX.8/2471']))
            ->assertOk()
            ->assertSee('PJ-BID-2026-10-002')
            ->assertDontSee('PJ-BID-2026-10-009');
    }

    /** Penghapusan sebelum cuplikan direkam tetap terdaftar, dengan keterangan isinya tak tersimpan. */
    public function test_penghapusan_lama_tanpa_cuplikan_tetap_tampil(): void
    {
        AuditLog::factory()->create([
            'aksi' => AuditLog::AKSI_DIHAPUS,
            'objek' => AuditLog::OBJEK_USULAN,
            'deskripsi' => 'Usulan USL-2025-001 dihapus.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('audit-log.penghapusan'))
            ->assertOk()
            ->assertSee('USL-2025-001')
            ->assertSee('isinya tidak tersimpan');
    }

    public function test_halaman_tertutup_bagi_peran_tanpa_hak_jejak_audit(): void
    {
        $this->actingAs($this->pengusul)->get(route('audit-log.penghapusan'))->assertForbidden();
    }

    /**
     * @param  array<string, string>  $rincian
     */
    private function penghapusan(string $objek, string $nomor, string $tujuan, array $rincian = []): AuditLog
    {
        return AuditLog::factory()->create([
            'id_user' => $this->pengusul->id,
            'aksi' => AuditLog::AKSI_DIHAPUS,
            'objek' => $objek,
            'deskripsi' => ($objek === AuditLog::OBJEK_SPD ? 'SPD ' : 'Usulan ').$nomor.' dihapus.',
            'cuplikan' => [
                'nomor' => $nomor,
                'pelaksana' => ['Rina Kusumawati'],
                'tujuan' => $tujuan,
                'tanggal_mulai' => '2026-10-12',
                'tanggal_selesai' => '2026-10-12',
                'maksud' => 'Rapat koordinasi.',
                'rincian' => $rincian,
            ],
        ]);
    }
}
