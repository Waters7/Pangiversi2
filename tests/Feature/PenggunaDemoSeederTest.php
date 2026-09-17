<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Database\Seeders\PegawaiPoltekkesSeeder;
use Database\Seeders\PenggunaDemoSeeder;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Akun demo: tujuh peran dengan satu sandi, siap dipakai berpindah-pindah
 * saat presentasi — dan tidak pernah menyentuh akun sungguhan di produksi.
 */
class PenggunaDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_menyiapkan_tujuh_akun_demo_dengan_sandi_yang_sama(): void
    {
        $this->seed([UnitKerjaSeeder::class, PenggunaDemoSeeder::class]);

        $akun = User::whereIn('nip', array_keys(PenggunaDemoSeeder::AKUN))->get()->keyBy('nip');

        $this->assertCount(7, $akun);

        foreach (PenggunaDemoSeeder::AKUN as $nip => $peran) {
            $this->assertSame($peran->value, $akun[$nip]->role, $nip);
            $this->assertTrue(Hash::check(PenggunaDemoSeeder::SANDI_DEMO, $akun[$nip]->password), $nip);
            $this->assertNotEmpty($akun[$nip]->nomor_rekening, $nip);
        }

        $direktur = $akun['197104041994031002'];
        $this->assertSame('Direktur', $direktur->jabatan);
        $this->assertNull($direktur->id_atasan);
        $this->assertSame($direktur->id, $akun['199310182025061003']->id_atasan);
        $this->assertSame(PeranPengguna::SuperAdministrator->value, $akun['199310182025061003']->role);
        $this->assertSame('DIR', $akun['198609262008122003']->unit->kode);
    }

    /** Dijalankan setelah seeder pegawai: akun yang sudah ada dialihkan ke peran dan sandi demo tanpa digandakan. */
    public function test_menimpa_peran_dan_sandi_akun_yang_sudah_ada_tanpa_menggandakan(): void
    {
        $this->seed([UnitKerjaSeeder::class, PegawaiPoltekkesSeeder::class]);
        $jumlah = User::count();

        $pengembang = User::firstWhere('nip', '199310182025061003');
        $this->assertSame(PeranPengguna::DosenTendik->value, $pengembang->role);

        $this->seed(PenggunaDemoSeeder::class);

        $this->assertSame($jumlah, User::count());
        $this->assertSame(PeranPengguna::SuperAdministrator->value, $pengembang->fresh()->role);
        $this->assertTrue(Hash::check(PenggunaDemoSeeder::SANDI_DEMO, $pengembang->fresh()->password));
    }

    public function test_menolak_berjalan_di_produksi(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $this->app->detectEnvironment(fn () => 'production');

        // Dipanggil langsung: perintah db:seed sendiri sudah meminta konfirmasi di produksi.
        (new PenggunaDemoSeeder)->run();

        $this->assertDatabaseMissing('users', ['nip' => '199310182025061003']);
    }
}
