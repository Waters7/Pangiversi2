<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\SpdPelaksana;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPD yang dibuat bersama pelaksana lain: satu pelaksana dapat dihapus
 * sendiri, sedangkan SPD dan pelaksana lainnya tetap.
 */
class HapusPelaksanaSpdTest extends TestCase
{
    use RefreshDatabase;

    private User $pembuat;

    private User $rekan;

    private SuratPerjalananDinas $spd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pembuat = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->rekan = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->spd = $this->terbitkanSpd($this->pembuat);
        $this->spd->pelaksana()->create([
            'urutan' => 2,
            'id_user' => $this->rekan->id,
            'nomor_surat' => SpdPelaksana::rakitNomor('77', 2026),
            'nama' => $this->rekan->nama,
            'nip' => $this->rekan->nip,
        ]);
    }

    private function baris(User $pengguna): SpdPelaksana
    {
        return $this->spd->pelaksana()->where('id_user', $pengguna->id)->sole();
    }

    public function test_pembuat_menghapus_satu_pelaksana_dan_yang_lain_tetap(): void
    {
        $this->actingAs($this->pembuat)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->pembuat)]))
            ->assertRedirect(route('spd.show', $this->spd))
            ->assertSessionHas('success');

        $sisa = $this->spd->fresh()->pelaksana;
        $this->assertModelExists($this->spd);
        $this->assertSame([$this->rekan->id], $sisa->pluck('id_user')->all());
        $this->assertSame(1, $sisa->first()->urutan);

        // Yang menghapus dirinya sendiri tidak perlu diberi tahu, begitu pula
        // pelaksana yang tetap.
        $this->assertSame(0, Notifikasi::where('judul', 'Anda dihapus dari SPD')->count());
    }

    public function test_pelaksana_yang_dihapus_orang_lain_diberi_tahu(): void
    {
        $this->actingAs($this->pembuat)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->rekan)]));

        $this->assertSame(1, Notifikasi::where('id_user', $this->rekan->id)->where('judul', 'Anda dihapus dari SPD')->count());
        $this->assertSame([$this->pembuat->id], $this->spd->fresh()->pelaksana->pluck('id_user')->all());
    }

    /** Pelaksana yang bukan pembuat menghapus dirinya sendiri, bukan SPD rekannya. */
    public function test_rekan_hanya_menghapus_dirinya_sendiri(): void
    {
        $this->actingAs($this->rekan)
            ->get(route('spd.show', $this->spd))
            ->assertOk()
            ->assertSee('Hapus Saya dari SPD')
            ->assertDontSee(route('spd.destroy', $this->spd).'"', false);

        $this->actingAs($this->rekan)->delete(route('spd.destroy', $this->spd))->assertForbidden();
        $this->actingAs($this->rekan)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->pembuat)]))
            ->assertForbidden();

        $this->actingAs($this->rekan)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->rekan)]))
            ->assertRedirect(route('spd.index'));

        $this->assertSame([$this->pembuat->id], $this->spd->fresh()->pelaksana->pluck('id_user')->all());
    }

    public function test_pelaksana_terakhir_tidak_dihapus_sendiri(): void
    {
        $this->baris($this->rekan)->delete();

        $this->actingAs($this->pembuat)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->pembuat)]))
            ->assertSessionHas('error');

        $this->assertSame(1, $this->spd->pelaksana()->count());
    }

    public function test_pelaksana_yang_sudah_mengajukan_usulan_tidak_dihapus(): void
    {
        $usulan = Usulan::factory()->create(['id_user' => $this->rekan->id, 'id_spd' => $this->spd->id, 'status' => 'draft']);

        $this->actingAs($this->pembuat)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->rekan)]))
            ->assertSessionHas('error', "{$this->rekan->nama} sudah mengajukan usulan {$usulan->no_usulan} dari SPD ini. Batalkan atau hapus usulan itu lebih dulu.");

        $this->assertSame(2, $this->spd->pelaksana()->count());

        $usulan->batalkanKeikutsertaan();

        $this->actingAs($this->pembuat)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $this->baris($this->rekan)]))
            ->assertSessionHas('success');
    }

    public function test_pelaksana_spd_lain_tidak_dapat_dihapus_lewat_spd_ini(): void
    {
        $lain = $this->terbitkanSpd($this->pembuat);
        $lain->pelaksana()->create([
            'urutan' => 2,
            'id_user' => $this->rekan->id,
            'nomor_surat' => SpdPelaksana::rakitNomor('78', 2026),
            'nama' => $this->rekan->nama,
        ]);

        $this->actingAs($this->pembuat)
            ->delete(route('spd.pelaksana.destroy', [$this->spd, $lain->pelaksana()->where('id_user', $this->rekan->id)->sole()]))
            ->assertNotFound();

        $this->assertSame(2, $lain->pelaksana()->count());
    }

    public function test_pembuat_tetap_dapat_menghapus_spd_utuh(): void
    {
        $this->actingAs($this->pembuat)
            ->delete(route('spd.destroy', $this->spd))
            ->assertRedirect(route('spd.index'));

        $this->assertModelMissing($this->spd);
    }
}
