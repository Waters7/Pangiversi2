<?php

namespace Tests\Feature;

use App\Enums\LevelPersetujuan;
use App\Enums\StatusUsulan;
use App\Models\Persetujuan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\WorkflowUsulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tahap validasi PPK sudah ditiadakan: penugasan disahkan lewat Surat
 * Perjalanan Dinas, sehingga usulan berlaku begitu diajukan.
 *
 * Mesin persetujuannya sendiri sengaja dipertahankan — usulan lama yang
 * terlanjur berstatus menunggu masih harus bisa diputuskan, dan tahapnya
 * dapat dipasang kembali tanpa membangun ulang. Berkas ini menjaga kedua
 * sisi itu: pengajuan baru tidak mengantre, keputusan lama tetap jalan.
 */
class PersetujuanBerjenjangTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private User $ppk;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppk = User::factory()->ppk()->create();
        $this->pimpinan = User::factory()->create(['role' => User::ROLE_PIMPINAN]);
        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    private function usulanBaru(string $status = StatusUsulan::Draft->value): Usulan
    {
        return Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => $status,
        ]);
    }

    private function workflow(): WorkflowUsulan
    {
        return app(WorkflowUsulan::class);
    }

    // ── Alur validasi ──

    public function test_pengajuan_langsung_berlaku_tanpa_menunggu_ppk(): void
    {
        $usulan = $this->usulanBaru();

        $this->workflow()->ajukan($usulan);

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);
        $this->assertTrue($this->workflow()->antrianUntuk($this->ppk)->isEmpty());
    }

    public function test_pengajuan_tidak_merekam_keputusan_maupun_menagih_ppk(): void
    {
        $usulan = $this->usulanBaru();

        $this->workflow()->ajukan($usulan);

        $this->assertSame(0, $usulan->persetujuan()->count());
        $this->assertDatabaseMissing('notifikasi', ['id_user' => $this->ppk->id]);
    }

    public function test_usulan_tetap_berlaku_walau_tidak_ada_pengguna_ppk(): void
    {
        $this->ppk->delete();
        $usulan = $this->usulanBaru();

        $this->workflow()->ajukan($usulan);

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);
    }

    public function test_keputusan_tercatat_dengan_level_peran_dan_approver(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->actingAs($this->ppk)->put(route('persetujuan.approve', $usulan), [
            'catatan' => 'Anggaran tersedia pada mata anggaran 5241',
        ]);

        $keputusan = $usulan->persetujuan()->first();

        $this->assertSame(LevelPersetujuan::Ppk, $keputusan->level);
        $this->assertSame(User::ROLE_PPK, $keputusan->peran);
        $this->assertSame($this->ppk->id, $keputusan->id_approver);
        $this->assertSame(Persetujuan::KEPUTUSAN_SETUJU, $keputusan->keputusan);
        $this->assertSame('Anggaran tersedia pada mata anggaran 5241', $keputusan->catatan);
    }

    // ── Kewenangan ──

    public function test_hanya_ppk_dan_super_administrator_yang_dapat_memutuskan(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        // Pimpinan memantau, tetapi tidak lagi menjadi tahap validasi.
        $this->actingAs($this->pimpinan)
            ->put(route('persetujuan.approve', $usulan))
            ->assertForbidden();

        foreach ([User::ROLE_TIM_SDM, User::ROLE_BENDAHARA, User::ROLE_TIM_KEUANGAN] as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran]))
                ->put(route('persetujuan.approve', $usulan))
                ->assertForbidden();
        }

        $this->assertSame(StatusUsulan::MenungguPpk->value, $usulan->fresh()->status);
    }

    public function test_super_administrator_dapat_memutuskan_sebagai_jaring_pengaman(): void
    {
        $admin = User::factory()->administrator()->create();
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->actingAs($admin)->put(route('persetujuan.approve', $usulan));

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);
    }

    public function test_usulan_yang_tidak_menunggu_keputusan_tidak_dapat_diputuskan(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::Disetujui->value);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.approve', $usulan))
            ->assertForbidden();
    }

    public function test_pengusul_tidak_dapat_membuka_menu_validasi(): void
    {
        $this->actingAs($this->pengusul)
            ->get(route('persetujuan'))
            ->assertForbidden();
    }

    public function test_atasan_langsung_tidak_lagi_memberi_akses_validasi(): void
    {
        $bawahan = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'id_atasan' => $this->pengusul->id,
        ]);

        $this->assertTrue($this->pengusul->bawahan()->whereKey($bawahan->id)->exists());

        $this->actingAs($this->pengusul)
            ->get(route('persetujuan'))
            ->assertForbidden();
    }

    public function test_pimpinan_dapat_memantau_halaman_validasi_tanpa_memutuskan(): void
    {
        $this->actingAs($this->pimpinan)
            ->get(route('persetujuan'))
            ->assertOk();

        $this->assertFalse($this->pimpinan->bisaMenyetujui());
        $this->assertTrue($this->pimpinan->bisaMembukaPersetujuan());
    }

    // ── Penolakan dan revisi ──

    public function test_penolakan_menghentikan_proses_dan_wajib_beralasan(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.reject', $usulan), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.reject', $usulan), ['catatan' => 'Dokumen tidak lengkap']);

        $usulan->refresh();

        $this->assertSame(StatusUsulan::Ditolak->value, $usulan->status);
        $this->assertSame('Dokumen tidak lengkap', $usulan->catatan);
        $this->assertSame(Persetujuan::KEPUTUSAN_TOLAK, $usulan->persetujuan()->first()->keputusan);
    }

    public function test_permintaan_revisi_mengembalikan_usulan_ke_pengusul(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.revisi', $usulan), ['catatan' => 'Lampirkan surat undangan']);

        $usulan->refresh();

        $this->assertSame(StatusUsulan::PerluRevisi->value, $usulan->status);
        $this->assertSame('Lampirkan surat undangan', $usulan->catatan);
        $this->assertTrue($usulan->bolehDisunting());

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pengusul->id,
            'judul' => 'Usulan perlu diperbaiki',
            'tipe' => 'peringatan',
        ]);
    }

    public function test_revisi_wajib_disertai_catatan(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.revisi', $usulan), ['catatan' => ''])
            ->assertSessionHasErrors('catatan');

        $this->assertSame(StatusUsulan::MenungguPpk->value, $usulan->fresh()->status);
    }

    public function test_pengajuan_ulang_setelah_revisi_langsung_berlaku(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->workflow()->mintaRevisi($usulan, $this->ppk, 'Perbaiki tanggal kegiatan');
        $this->workflow()->ajukan($usulan->fresh());

        $this->assertSame(StatusUsulan::Disetujui->value, $usulan->fresh()->status);

        // Catatan revisi dibersihkan agar tidak tersisa di layar pengusul.
        $this->assertNull($usulan->fresh()->catatan);
    }

    // ── Antrian ──

    public function test_antrian_ppk_hanya_berisi_usulan_yang_menunggu_validasi(): void
    {
        $menunggu = $this->usulanBaru(StatusUsulan::MenungguPpk->value);
        $sudahSelesai = $this->usulanBaru(StatusUsulan::Disetujui->value);

        $antrian = $this->workflow()->antrianUntuk($this->ppk);

        $this->assertTrue($antrian->contains('id', $menunggu->id));
        $this->assertFalse($antrian->contains('id', $sudahSelesai->id));
    }

    public function test_halaman_antrian_menampilkan_jumlah_yang_menunggu_keputusan(): void
    {
        $this->usulanBaru(StatusUsulan::MenungguPpk->value);
        $this->usulanBaru(StatusUsulan::MenungguPpk->value);
        $this->usulanBaru(StatusUsulan::Disetujui->value);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan', ['tab' => 'antrian']))
            ->assertOk()
            ->assertViewHas('jumlahAntrian', 2);
    }

    /**
     * Detail usulan tidak lagi memuat rantai persetujuan: halamannya
     * menjawab "berkas ini sudah sampai mana" lewat pelacakan tonggak.
     * Rantainya tetap ada pada halaman persetujuan, tempat ia dipakai
     * mengambil keputusan.
     */
    public function test_detail_usulan_tidak_lagi_memuat_rantai_persetujuan(): void
    {
        $usulan = $this->usulanBaru(StatusUsulan::MenungguPpk->value);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $usulan))
            ->assertOk()
            ->assertSee('Pelacakan Berkas')
            ->assertDontSee('Rantai Persetujuan');
    }
}
