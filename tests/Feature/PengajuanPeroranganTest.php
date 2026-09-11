<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\Persetujuan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengajuan perjadin selalu perorangan.
 *
 * Tiap pelaksana mengunggah SPD bertanda tangannya sendiri beserta nomor
 * resminya, jadi tidak ada lagi pengajuan berkelompok yang membuatkan
 * usulan untuk rekan dan menunggu konfirmasi mereka. SPD bertanda tangan
 * itulah dasar persetujuan PPK yang tercatat pada jejak audit.
 */
class PengajuanPeroranganTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->ppk = User::factory()->ppk()->create(['nama' => 'Pejabat Pembuat Komitmen']);

        $this->pengusul = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'nama' => 'Pelaksana Perjadin',
        ]);

        $this->terbitkanSpd($this->pengusul);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function dataUsulan(array $ubahan = []): array
    {
        return array_merge([
            'id_spd' => $this->spdMilik($this->pengusul)->id,
            'id_kegiatan' => Kegiatan::factory()->create()->id,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create()->id,
            'no_tugas' => 'TGS-2026-010',
            'no_spd' => 'KU.02.04/F.XXX.8/1234/2026',
            'lokasi' => 'Manado',
            'instansi' => 'Dinas Kesehatan',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2026-09-03',
            'surat_tugas' => UploadedFile::fake()->create('surat-tugas.pdf', 100, 'application/pdf'),
            'spd_ditandatangani' => UploadedFile::fake()->create('spd.pdf', 100, 'application/pdf'),
        ], $ubahan);
    }

    // ── Selalu perorangan ──

    public function test_pengajuan_membuat_satu_usulan_yang_langsung_berlaku(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan())
            ->assertSessionHas('success', 'Pengajuan perjadin berhasil dikirim.');

        $this->assertSame(1, Usulan::count());

        $usulan = Usulan::first();

        $this->assertSame($this->pengusul->id, $usulan->id_user);
        $this->assertSame(Usulan::PENGAJUAN_PERSONAL, $usulan->jenis_pengajuan);
        $this->assertNull($usulan->kode_rombongan);
        $this->assertFalse($usulan->dibuatkanOrangLain());
        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->status);
    }

    /** Kiriman lama berpola rombongan tidak lagi membuatkan usulan orang lain. */
    public function test_kiriman_berkelompok_diabaikan(): void
    {
        $rekan = User::factory()->create(['nama' => 'Rekan Satu']);

        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan([
            'jenis_pengajuan' => 'kelompok',
            'anggota' => [$rekan->id],
        ]));

        $this->assertSame(1, Usulan::count());
        $this->assertSame(0, Usulan::where('id_user', $rekan->id)->count());
        $this->assertDatabaseMissing('notifikasi', ['id_user' => $rekan->id]);
    }

    public function test_formulir_tidak_lagi_menawarkan_pengajuan_berkelompok(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertDontSee('Berkelompok')
            ->assertDontSee('Kirim untuk Dikonfirmasi')
            ->assertDontSee('name="jenis_pengajuan"', false)
            ->assertSee('Kirim Pengajuan Perjadin');
    }

    // ── SPD bertanda tangan wajib ──

    public function test_nomor_spd_wajib_diisi(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $this->dataUsulan(['no_spd' => '']))
            ->assertSessionHasErrors('no_spd');

        $this->assertSame(0, Usulan::count());
    }

    public function test_berkas_spd_bertanda_tangan_wajib_diunggah(): void
    {
        $data = $this->dataUsulan();
        unset($data['spd_ditandatangani']);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.store'), $data)
            ->assertSessionHasErrors('spd_ditandatangani');

        $this->assertSame(0, Usulan::count());
    }

    public function test_nomor_dan_berkas_spd_tersimpan(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $usulan = Usulan::first();

        $this->assertSame('KU.02.04/F.XXX.8/1234/2026', $usulan->no_spd);
        $this->assertTrue($usulan->punyaSpdBertandaTangan());
        Storage::disk('public')->assertExists($usulan->berkasSpdBertandaTangan());
    }

    public function test_formulir_meminta_spd_bertanda_tangan(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('usulan.create'))
            ->assertOk()
            ->assertSee('Surat Perjalanan Dinas Bertanda Tangan')
            ->assertSee('name="spd_ditandatangani"', false)
            ->assertSee('name="no_spd"', false);
    }

    // ── Draf lama tanpa SPD bertanda tangan ──

    public function test_draf_tanpa_spd_bertanda_tangan_tidak_dapat_dikirim(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Draft->value,
            'no_spd' => null,
        ]);

        $this->actingAs($this->pengusul)
            ->put(route('usulan.ajukan', $usulan))
            ->assertSessionHas('error');

        $this->assertSame(StatusUsulan::Draft->value, $usulan->fresh()->status);
    }

    public function test_draf_yang_lengkap_dapat_dikirim(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Draft->value,
        ]);
        Dokumen::factory()->create(['id_usulan' => $usulan->id]);

        $this->actingAs($this->pengusul)
            ->put(route('usulan.ajukan', $usulan))
            ->assertSessionHas('success');

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);
    }

    public function test_tombol_kirim_pengajuan_tampil_pada_draf_sendiri(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => StatusUsulan::Draft->value,
        ]);
        Dokumen::factory()->create(['id_usulan' => $usulan->id]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $usulan))
            ->assertOk()
            ->assertSee('Kirim Pengajuan Perjadin')
            ->assertDontSee('Ajukan Usulan Perjadin')
            ->assertSee(route('usulan.ajukan', $usulan), false);
    }

    // ── Jejak audit persetujuan PPK ──

    public function test_persetujuan_ppk_tercatat_dengan_nomor_spd(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $usulan = Usulan::first();

        $this->assertDatabaseHas('audit_logs', [
            'id_usulan' => $usulan->id,
            'aksi' => AuditLog::AKSI_DISETUJUI,
        ]);

        $catatan = AuditLog::where('id_usulan', $usulan->id)
            ->where('aksi', AuditLog::AKSI_DISETUJUI)
            ->value('deskripsi');

        $this->assertStringContainsString('PPK Pejabat Pembuat Komitmen menyetujui', $catatan);
        $this->assertStringContainsString('KU.02.04/F.XXX.8/1234/2026', $catatan);
    }

    public function test_persetujuan_ppk_terekam_pada_rantai_persetujuan(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $persetujuan = Usulan::first()->persetujuan()->first();

        $this->assertNotNull($persetujuan);
        $this->assertSame($this->ppk->id, $persetujuan->id_approver);
        $this->assertSame(Persetujuan::KEPUTUSAN_SETUJU, $persetujuan->keputusan);
        $this->assertStringContainsString('KU.02.04/F.XXX.8/1234/2026', $persetujuan->catatan);
    }

    // ── Kerahasiaan daftar ──

    public function test_daftar_usulan_tidak_membocorkan_usulan_pegawai_lain(): void
    {
        $this->actingAs($this->pengusul)->post(route('usulan.store'), $this->dataUsulan());

        $orangLain = User::factory()->create();
        $usulanOrangLain = Usulan::factory()->create(['id_user' => $orangLain->id]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.list'))
            ->assertOk()
            ->assertViewHas('usulan', fn ($daftar) => $daftar->getCollection()
                ->pluck('id')
                ->doesntContain($usulanOrangLain->id));
    }
}
