<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        // Usulan perjadin baru terbuka setelah SPD terbit.
        $this->terbitkanSpd($this->pengusul);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataUsulan(array $ubahan = []): array
    {
        return array_merge([
            'id_spd' => $this->spdMilik($this->pengusul)->id,
            'id_kegiatan' => Kegiatan::factory()->create()->id,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create()->id,
            'no_tugas' => 'TGS-2026-001',
            'lokasi' => 'Manado',
            'instansi' => 'Dinas Kesehatan',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-03',
            'uraian' => 'Rapat koordinasi program',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
            'no_spd' => 'KU.02.04/F.XXX.8/1234/2026',
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
        ], $ubahan);
    }

    public function test_membuat_draft_usulan_tercatat_di_jejak_audit(): void
    {
        Storage::fake('public');

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['action' => 'draft']));

        $log = AuditLog::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame(AuditLog::AKSI_DIBUAT, $log->aksi);
        $this->assertSame($this->pengusul->id, $log->id_user);
        $this->assertSame('draft', $log->status_baru);
    }

    public function test_mengajukan_usulan_mencatat_audit_dan_memberi_tahu_pengusul(): void
    {
        Storage::fake('public');

        // Tidak ada lagi tahap validasi: penugasannya sudah disahkan lewat SPD,
        // jadi usulan langsung berlaku dan yang diberi tahu adalah pengusulnya.
        $ppk = User::factory()->ppk()->create();

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan());

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => AuditLog::AKSI_DIAJUKAN,
            'id_user' => $this->pengusul->id,
            'status_baru' => StatusUsulan::Disetujui->value,
        ]);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pengusul->id,
            'judul' => 'Usulan perjalanan dinas Anda tercatat',
        ]);

        $this->assertDatabaseMissing('notifikasi', ['id_user' => $ppk->id]);
    }

    public function test_validasi_ppk_mencatat_perubahan_status_dan_memberi_tahu_pengusul(): void
    {
        $ppkPemutus = User::factory()->ppk()->create();
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::MenungguPpk->value,
        ]);

        $this->actingAs($ppkPemutus)->put(route('persetujuan.approve', $usulan));

        $log = AuditLog::where('aksi', AuditLog::AKSI_DISETUJUI)->first();

        $this->assertNotNull($log);
        $this->assertSame(StatusUsulan::MenungguPpk->value, $log->status_lama);
        $this->assertSame(StatusUsulan::Disetujui->value, $log->status_baru);
        $this->assertSame($ppkPemutus->id, $log->id_user);
        $this->assertSame($usulan->id, $log->id_usulan);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pengusul->id,
            'judul' => 'Usulan Anda disetujui',
            'tipe' => 'sukses',
        ]);
    }

    public function test_menolak_usulan_menyimpan_alasan_pada_jejak_audit(): void
    {
        $ppk = User::factory()->ppk()->create();
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::MenungguPpk->value,
        ]);

        $this->actingAs($ppk)->put(route('persetujuan.reject', $usulan), [
            'catatan' => 'Anggaran tidak tersedia',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => AuditLog::AKSI_DITOLAK,
            'catatan' => 'Anggaran tidak tersedia',
            'status_baru' => StatusUsulan::Ditolak->value,
        ]);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pengusul->id,
            'tipe' => 'bahaya',
        ]);
    }

    public function test_menghapus_usulan_tetap_tercatat_walau_usulan_hilang(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => 'draft',
        ]);

        $noUsulan = $usulan->no_usulan;

        $this->actingAs($this->pengusul)->delete(route('usulan.destroy', $usulan));

        $this->assertDatabaseMissing('usulan', ['id' => $usulan->id]);
        $this->assertDatabaseHas('audit_logs', [
            'aksi' => AuditLog::AKSI_DIHAPUS,
            'deskripsi' => "Usulan {$noUsulan} dihapus.",
        ]);
    }

    public function test_jejak_audit_menyimpan_alamat_ip_pelaku(): void
    {
        $ppk = User::factory()->ppk()->create();
        $usulan = Usulan::factory()->create(['status' => StatusUsulan::MenungguPpk->value]);

        $this->actingAs($ppk)->put(route('persetujuan.approve', $usulan));

        $this->assertNotNull(AuditLog::where('aksi', AuditLog::AKSI_DISETUJUI)->value('ip_address'));
    }

    public function test_halaman_jejak_audit_hanya_untuk_administrator(): void
    {
        $this->actingAs($this->pengusul)->get(route('audit-log'))->assertForbidden();
        $this->actingAs(User::factory()->ppk()->create())->get(route('audit-log'))->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get(route('audit-log'))->assertOk();
    }

    public function test_halaman_jejak_audit_dapat_difilter_berdasarkan_aksi(): void
    {
        $admin = User::factory()->administrator()->create();

        AuditLog::factory()->create(['aksi' => AuditLog::AKSI_DISETUJUI, 'deskripsi' => 'Catatan persetujuan']);
        AuditLog::factory()->create(['aksi' => AuditLog::AKSI_PEMBAYARAN, 'deskripsi' => 'Catatan pembayaran']);

        $this->actingAs($admin)
            ->get(route('audit-log', ['aksi' => AuditLog::AKSI_DISETUJUI]))
            ->assertOk()
            ->assertSee('Catatan persetujuan')
            ->assertDontSee('Catatan pembayaran');
    }

    /**
     * Detail usulan kini menampilkan pelacakan tonggak berkas, bukan jejak
     * audit: yang dicari di sana adalah sudah sampai mana berkasnya.
     * Jejak audit lengkap tetap tersedia pada menunya sendiri.
     */
    public function test_detail_usulan_menampilkan_pelacakan_bukan_jejak_audit(): void
    {
        $usulan = Usulan::factory()->create(['id_user' => $this->pengusul->id]);

        AuditLog::factory()->create([
            'id_usulan' => $usulan->id,
            'id_user' => $this->pengusul->id,
            'deskripsi' => 'Usulan diajukan ke atasan langsung',
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $usulan))
            ->assertOk()
            ->assertSee('Pelacakan Berkas')
            ->assertSee('Usulan dibuat')
            ->assertDontSee('Jejak Audit Usulan')
            ->assertDontSee('Usulan diajukan ke atasan langsung');
    }
}
