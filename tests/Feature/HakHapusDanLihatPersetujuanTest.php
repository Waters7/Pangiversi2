<?php

namespace Tests\Feature;

use App\Enums\Kemampuan;
use App\Enums\MenuAplikasi;
use App\Enums\PeranPengguna;
use App\Models\DaftarNominatif;
use App\Models\Peran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua hak akses yang dipecah dari yang sudah ada: menghapus komponen
 * rincian biaya (menu Keuangan → Hapus) dan melihat menu Persetujuan tanpa
 * ikut menandatangani (menu Persetujuan → Lihat).
 */
class HakHapusDanLihatPersetujuanTest extends TestCase
{
    use RefreshDatabase;

    private DaftarNominatif $nominatif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nominatif = DaftarNominatif::firstOrCreate(['no_tugas' => 'KP.03.01/F.XXXVIII/64/2026']);
    }

    /**
     * @param  list<Kemampuan>  $kemampuan
     */
    private function penggunaDengan(array $kemampuan): User
    {
        Peran::sinkronBawaan();
        Peran::where('kode', PeranPengguna::TimKeuangan->value)->sole()->aturHakAkses($kemampuan);

        return User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
    }

    public function test_kedua_hak_ada_pada_kolomnya_di_matriks(): void
    {
        $this->assertSame(MenuAplikasi::Keuangan, MenuAplikasi::untuk(Kemampuan::MenghapusRincianBiaya));
        $this->assertSame(MenuAplikasi::Persetujuan, MenuAplikasi::untuk(Kemampuan::MelihatPersetujuan));
        $this->assertTrue(PeranPengguna::TimKeuangan->punya(Kemampuan::MenghapusRincianBiaya));
        $this->assertTrue(PeranPengguna::Ppk->punya(Kemampuan::MelihatPersetujuan));
    }

    public function test_hak_melihat_persetujuan_membuka_menu_tanpa_tombol_tanda_tangan(): void
    {
        $pemantau = $this->penggunaDengan([Kemampuan::MelihatKeuangan, Kemampuan::MelihatPersetujuan]);

        $this->actingAs($pemantau)->get(route('persetujuan.rincian-biaya'))->assertOk();
        $this->actingAs($pemantau)->get(route('persetujuan.daftar-riil'))->assertOk();
        $this->actingAs($pemantau)->get(route('persetujuan.riwayat'))->assertOk();
        $this->actingAs($pemantau)
            ->get(route('persetujuan.nominatif.detail', $this->nominatif))
            ->assertOk()
            ->assertSee('Menunggu tanda tangan PPK.')
            ->assertDontSee(route('persetujuan.nominatif.tanda-tangan', $this->nominatif));

        $this->actingAs($pemantau)
            ->put(route('persetujuan.nominatif.tanda-tangan', $this->nominatif))
            ->assertForbidden();

        $this->assertFalse($this->nominatif->fresh()->sudahDitandatangani());
    }

    public function test_tanpa_hak_melihat_persetujuan_menunya_tertutup(): void
    {
        $penyusun = $this->penggunaDengan([Kemampuan::MelihatKeuangan, Kemampuan::MengelolaBiaya]);

        $this->actingAs($penyusun)->get(route('persetujuan.rincian-biaya'))->assertForbidden();
        $this->actingAs($penyusun)->get(route('dashboard'))->assertDontSee(route('persetujuan.rincian-biaya'));
    }

    /** Yang berhak menandatangani tetap dapat membuka menunya walau kotak lihatnya kosong. */
    public function test_penandatangan_tetap_dapat_membuka_dan_menandatangani(): void
    {
        $penandatangan = $this->penggunaDengan([Kemampuan::MenandatanganiDaftarRiil]);

        $this->actingAs($penandatangan)
            ->get(route('persetujuan.nominatif.detail', $this->nominatif))
            ->assertOk()
            ->assertSee(route('persetujuan.nominatif.tanda-tangan', $this->nominatif));

        $this->actingAs($penandatangan)
            ->put(route('persetujuan.nominatif.tanda-tangan', $this->nominatif))
            ->assertSessionHas('success');

        $this->assertTrue($this->nominatif->fresh()->sudahDitandatangani());
    }

    /**
     * Peran yang hak aksesnya sudah tersimpan tidak kehilangan kemampuan
     * yang selama ini ia pakai: migrasinya memberi hak baru menurut hak lama.
     */
    public function test_migrasi_memberi_hak_baru_menurut_hak_lama(): void
    {
        Peran::sinkronBawaan();
        $timKeuangan = Peran::where('kode', PeranPengguna::TimKeuangan->value)->sole();
        $ppk = Peran::where('kode', PeranPengguna::Ppk->value)->sole();
        $timSdm = Peran::where('kode', PeranPengguna::TimSdm->value)->sole();
        DB::table('hak_akses_peran')
            ->whereIn('kemampuan', [Kemampuan::MenghapusRincianBiaya->value, Kemampuan::MelihatPersetujuan->value])
            ->delete();

        $migrasi = require database_path('migrations/2026_10_08_124857_beri_hak_hapus_rincian_dan_lihat_persetujuan.php');
        $migrasi->up();

        $this->assertTrue($timKeuangan->fresh()->punya(Kemampuan::MenghapusRincianBiaya));
        $this->assertTrue($ppk->fresh()->punya(Kemampuan::MelihatPersetujuan));
        $this->assertFalse($timSdm->fresh()->punya(Kemampuan::MenghapusRincianBiaya));
        $this->assertFalse($timSdm->fresh()->punya(Kemampuan::MelihatPersetujuan));
    }
}
