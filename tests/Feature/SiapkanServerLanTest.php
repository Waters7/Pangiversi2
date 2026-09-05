<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Alamat IP perangkat pada jaringan kantor diberikan DHCP dan berganti
 * sendiri. Perintah penyiapan harus menyelaraskan APP_URL dengan alamat
 * yang berlaku saat ini, karena tautan pada notifikasi buatan penjadwal
 * disusun dari nilai tersebut.
 */
class SiapkanServerLanTest extends TestCase
{
    private ?string $envAsli = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Berkas .env sungguhan disimpan dan dipulihkan agar pengujian
        // tidak merusak konfigurasi pengembang.
        if (File::exists(base_path('.env'))) {
            $this->envAsli = File::get(base_path('.env'));
        }
    }

    protected function tearDown(): void
    {
        if ($this->envAsli !== null) {
            File::put(base_path('.env'), $this->envAsli);
        }

        parent::tearDown();
    }

    public function test_app_url_diselaraskan_dengan_alamat_yang_diberikan(): void
    {
        File::put(base_path('.env'), "APP_NAME=PANGI\nAPP_URL=http://172.16.70.205:8000\nAPP_DEBUG=false\n");

        $this->artisan('pangi:siapkan-lan', ['--ip' => '192.168.1.50'])
            ->assertSuccessful();

        $isi = File::get(base_path('.env'));

        $this->assertStringContainsString('APP_URL=http://192.168.1.50:8000', $isi);
        $this->assertStringNotContainsString('172.16.70.205', $isi);
    }

    public function test_baris_lain_pada_env_tidak_ikut_berubah(): void
    {
        File::put(base_path('.env'), "APP_NAME=PANGI\nAPP_URL=http://lama:8000\nDB_CONNECTION=sqlite\nAPP_LOCALE=id\n");

        $this->artisan('pangi:siapkan-lan', ['--ip' => '10.10.0.7'])->assertSuccessful();

        $isi = File::get(base_path('.env'));

        $this->assertStringContainsString('APP_NAME=PANGI', $isi);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $isi);
        $this->assertStringContainsString('APP_LOCALE=id', $isi);
    }

    public function test_app_url_ditambahkan_bila_belum_ada(): void
    {
        File::put(base_path('.env'), "APP_NAME=PANGI\nDB_CONNECTION=sqlite\n");

        $this->artisan('pangi:siapkan-lan', ['--ip' => '10.0.0.5'])->assertSuccessful();

        $this->assertStringContainsString('APP_URL=http://10.0.0.5:8000', File::get(base_path('.env')));
    }

    public function test_porta_dapat_ditentukan(): void
    {
        File::put(base_path('.env'), "APP_URL=http://lama:8000\n");

        $this->artisan('pangi:siapkan-lan', ['--ip' => '192.168.4.4', '--port' => 9001])
            ->assertSuccessful();

        $this->assertStringContainsString('APP_URL=http://192.168.4.4:9001', File::get(base_path('.env')));
    }

    public function test_alamat_yang_tidak_sah_ditolak(): void
    {
        File::put(base_path('.env'), "APP_URL=http://lama:8000\n");

        $this->artisan('pangi:siapkan-lan', ['--ip' => 'bukan-alamat'])
            ->expectsOutputToContain('bukan alamat IPv4 yang sah')
            ->assertFailed();

        // Berkas tidak boleh tersentuh saat masukannya ditolak.
        $this->assertStringContainsString('APP_URL=http://lama:8000', File::get(base_path('.env')));
    }

    public function test_alamat_dan_petunjuk_firewall_ditampilkan(): void
    {
        File::put(base_path('.env'), "APP_URL=http://lama:8000\n");

        $this->artisan('pangi:siapkan-lan', ['--ip' => '172.16.70.246'])
            ->expectsOutputToContain('http://localhost:8000')
            ->expectsOutputToContain('http://172.16.70.246:8000')
            ->expectsOutputToContain('New-NetFirewallRule')
            ->assertSuccessful();
    }
}
