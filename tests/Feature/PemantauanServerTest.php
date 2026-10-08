<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\CatatanPenyimpanan;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\PemantauServer;
use App\Services\PengukurPenyimpanan;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Menu Pemantauan Server: ukuran aplikasi di peladen, dan peringatan
 * WhatsApp yang dikirim pemantau tiap menit bila aplikasi tidak dapat
 * diakses — termasuk saat basis datanya sendiri yang terputus.
 */
class PemantauanServerTest extends TestCase
{
    use RefreshDatabase;

    private string $kerja;

    protected function setUp(): void
    {
        parent::setUp();

        // Folder aplikasi tiruan dan folder status sendiri, supaya pengujian
        // tidak menjelajahi atau menulisi folder aplikasi yang sebenarnya.
        $this->kerja = sys_get_temp_dir().'/pangi-pantau-'.uniqid();
        mkdir($this->kerja.'/aplikasi', 0777, true);

        config([
            'app.url' => 'https://pangi.test',
            'pantau.akar' => $this->kerja.'/aplikasi',
            'pantau.folder' => $this->kerja.'/pantau',
            'pantau.ambang_gagal' => 3,
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->kerja);

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    private function berkas(string $jalur, int $ukuran): void
    {
        $penuh = $this->kerja.'/aplikasi/'.$jalur;
        @mkdir(dirname($penuh), 0777, true);
        file_put_contents($penuh, str_repeat('x', $ukuran));
    }

    /**
     * @param  array<string, mixed>  $ubahan
     */
    private function simpanPeringatan(array $ubahan = []): TestResponse
    {
        return $this->actingAs($this->admin())->put(route('administrasi.server.peringatan'), array_merge([
            'peringatan_aktif' => '1',
            'nomor_penerima' => "0812 3456 7890,\n0853-1111-2222",
            'wa_gateway' => 'fonnte',
            'wa_token' => 'TOKENFONNTE123456',
            'peringatan_ulang_menit' => 60,
        ], $ubahan));
    }

    /**
     * @return list<PermintaanHttp>
     */
    private function kirimanWhatsapp(): array
    {
        return Http::recorded(fn (PermintaanHttp $permintaan) => str_contains($permintaan->url(), 'api.fonnte.com'))
            ->map(fn (array $pasangan) => $pasangan[0])
            ->values()
            ->all();
    }

    // ── Akses ──

    public function test_hanya_super_administrator_yang_membuka_pemantauan_server(): void
    {
        $this->actingAs($this->admin())
            ->get(route('administrasi.server'))
            ->assertOk()
            ->assertSee('Ukuran Aplikasi')
            ->assertSee('Ukuran aplikasi belum pernah diukur.')
            ->assertSee('Pemeriksaan otomatis belum berjalan')
            ->assertSee('php artisan pangi:pantau-server');

        $timSdm = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);
        $this->actingAs($timSdm)->get(route('administrasi.server'))->assertForbidden();
        $this->actingAs($timSdm)->post(route('administrasi.server.ukur'))->assertForbidden();
        $this->actingAs($timSdm)->put(route('administrasi.server.peringatan'), [])->assertForbidden();
        $this->actingAs($timSdm)->post(route('administrasi.server.uji'))->assertForbidden();
    }

    // ── Ukuran aplikasi ──

    public function test_ukur_ulang_mencatat_ukuran_per_kelompok(): void
    {
        $this->berkas('storage/app/public/dokumen/boarding-pass/a.pdf', 1000);
        $this->berkas('storage/app/public/foto-profil/b.jpg', 400);
        $this->berkas('storage/logs/laravel.log', 500);
        $this->berkas('vendor/paket/kelas.php', 300);
        $this->berkas('app/Kelas.php', 200);
        // Tidak dihitung: node_modules tidak dipasang di peladen, dan
        // public/storage hanyalah tautan ke berkas unggahan.
        $this->berkas('node_modules/besar.js', 9000);
        $this->berkas('public/storage/dokumen/a.pdf', 1000);

        $admin = $this->admin();

        $this->actingAs($admin)->post(route('administrasi.server.ukur'))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('administrasi.server.ukur'))->assertSessionHas('success');

        $this->assertSame(1, CatatanPenyimpanan::count());

        $catatan = CatatanPenyimpanan::first();
        $kelompok = $catatan->rincian['kelompok'];

        $this->assertSame(1400, $catatan->unggahan_byte);
        $this->assertSame(500, $kelompok['log']['byte']);
        $this->assertSame(300, $kelompok['pustaka']['byte']);
        $this->assertSame(200, $kelompok['aplikasi']['byte']);
        $this->assertSame(array_sum(array_column($kelompok, 'byte')), $catatan->total_byte);
        $this->assertSame('dokumen/boarding-pass', $catatan->rincian['unggahan'][0]['folder']);

        $this->actingAs($admin)
            ->get(route('administrasi.server'))
            ->assertOk()
            ->assertSee('Boarding pass')
            ->assertSee('storage/logs/laravel.log');
    }

    public function test_basis_data_sqlite_di_luar_folder_aplikasi_diukur_dari_berkasnya(): void
    {
        $berkas = $this->kerja.'/data/pangi.sqlite';
        mkdir(dirname($berkas));
        (new \PDO('sqlite:'.$berkas))->exec('create table contoh (isi text); insert into contoh values ("'.str_repeat('x', 5000).'");');

        $semula = config('database.default');
        config([
            'database.connections.berkas' => ['driver' => 'sqlite', 'database' => $berkas, 'prefix' => ''],
            'database.default' => 'berkas',
        ]);

        try {
            $hasil = app(PengukurPenyimpanan::class)->ukur();
        } finally {
            config(['database.default' => $semula]);
            DB::purge('berkas');
        }

        $this->assertSame(filesize($berkas), $hasil['kelompok']['basis_data']['byte']);
        $this->assertTrue($hasil['basis_data']['terukur']);
    }

    public function test_kuota_hosting_menampilkan_persentase_pemakaian(): void
    {
        CatatanPenyimpanan::factory()->create([
            'tanggal' => today()->toDateString(),
            'total_byte' => 9 * 1024 ** 3,
        ]);

        $this->actingAs($this->admin())
            ->put(route('administrasi.server.kuota'), ['kuota_gb' => 10])
            ->assertSessionHas('success');

        $this->assertSame('10240', Pengaturan::ambil(Pengaturan::KUOTA_PENYIMPANAN_MB));

        $this->actingAs($this->admin())
            ->get(route('administrasi.server'))
            ->assertSee('(90%)', false)
            ->assertSee('Kuota hampir habis');
    }

    // ── Pengaturan peringatan ──

    public function test_pengaturan_peringatan_tersimpan_dengan_token_terenkripsi(): void
    {
        $this->simpanPeringatan()->assertSessionHas('success');

        $this->assertSame('6281234567890,6285311112222', Pengaturan::ambil(Pengaturan::PERINGATAN_NOMOR));
        $this->assertSame('TOKENFONNTE123456', Pengaturan::rahasia(Pengaturan::WA_TOKEN));
        $this->assertNotSame('TOKENFONNTE123456', Pengaturan::ambil(Pengaturan::WA_TOKEN));

        // Cermin untuk saat basis data terputus juga tidak memuat token apa adanya.
        $cermin = (string) file_get_contents($this->kerja.'/pantau/pengaturan-peringatan.json');
        $this->assertStringContainsString('6281234567890', $cermin);
        $this->assertStringNotContainsString('TOKENFONNTE123456', $cermin);
    }

    public function test_nomor_penerima_yang_tidak_dikenali_ditolak(): void
    {
        $this->simpanPeringatan(['nomor_penerima' => '0812 3456 7890, 123'])
            ->assertSessionHasErrors(['nomor_penerima' => 'Nomor "123" tidak dikenali sebagai nomor WhatsApp.']);

        $this->simpanPeringatan(['nomor_penerima' => ''])->assertSessionHasErrors('nomor_penerima');
    }

    public function test_wablas_wajib_beralamat_peladen_dan_dikirim_per_nomor(): void
    {
        $this->simpanPeringatan(['wa_gateway' => 'wablas'])->assertSessionHasErrors('wa_url_wablas');

        $this->simpanPeringatan(['wa_gateway' => 'wablas', 'wa_url_wablas' => 'https://tegal.wablas.com'])->assertSessionHas('success');

        Http::fake(['tegal.wablas.com/*' => Http::response(['status' => true])]);

        $this->actingAs($this->admin())->post(route('administrasi.server.uji'))->assertSessionHas('success');

        Http::assertSentCount(2);
        Http::assertSent(fn (PermintaanHttp $permintaan) => $permintaan->url() === 'https://tegal.wablas.com/api/send-message'
            && $permintaan['phone'] === '6285311112222'
            && $permintaan->hasHeader('Authorization', 'TOKENFONNTE123456'));
    }

    public function test_pesan_uji_dikirim_lewat_fonnte_dengan_token(): void
    {
        $this->simpanPeringatan();

        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'detail' => 'success'])]);

        $this->actingAs($this->admin())
            ->post(route('administrasi.server.uji'))
            ->assertSessionHas('success');

        Http::assertSent(fn (PermintaanHttp $permintaan) => $permintaan->url() === 'https://api.fonnte.com/send'
            && $permintaan->hasHeader('Authorization', 'TOKENFONNTE123456')
            && $permintaan['target'] === '6281234567890,6285311112222'
            && str_contains($permintaan['message'], 'Uji peringatan PANGI'));

        $this->assertSame(PemantauServer::JENIS_UJI, app(PemantauServer::class)->status()['riwayat'][0]['jenis']);
    }

    public function test_penolakan_gateway_ditampilkan_apa_adanya(): void
    {
        $this->simpanPeringatan();

        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'invalid token'])]);

        $this->actingAs($this->admin())
            ->post(route('administrasi.server.uji'))
            ->assertSessionHas('error', 'Pesan uji gagal: Fonnte menolak: invalid token');
    }

    // ── Pemantau tiap menit ──

    public function test_gangguan_diumumkan_setelah_gagal_berturut_turut_lalu_kabar_pulih(): void
    {
        $this->simpanPeringatan();

        Http::fake([
            'pangi.test/up' => Http::sequence()
                ->push('galat', 500)->push('galat', 500)->push('galat', 500)->push('galat', 500)->push('galat', 500)
                ->push('ok', 200),
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);

        // Dua kegagalan pertama belum dianggap gangguan.
        $this->artisan('pangi:pantau-server')->assertFailed();
        $this->artisan('pangi:pantau-server')->assertFailed();
        $this->assertCount(0, $this->kirimanWhatsapp());

        $this->artisan('pangi:pantau-server')->assertFailed();
        $this->assertCount(1, $this->kirimanWhatsapp());
        $this->assertStringContainsString('PANGI tidak dapat diakses', $this->kirimanWhatsapp()[0]['message']);
        $this->assertStringContainsString('Situs: Membalas galat HTTP 500', $this->kirimanWhatsapp()[0]['message']);

        // Selama jeda pengingat belum lewat, tidak dikirim ulang.
        $this->artisan('pangi:pantau-server')->assertFailed();
        $this->assertCount(1, $this->kirimanWhatsapp());

        $this->travel(61)->minutes();
        $this->artisan('pangi:pantau-server')->assertFailed();
        $this->assertCount(2, $this->kirimanWhatsapp());
        $this->assertStringContainsString('masih belum dapat diakses', $this->kirimanWhatsapp()[1]['message']);

        $this->artisan('pangi:pantau-server')->assertSuccessful();
        $this->assertCount(3, $this->kirimanWhatsapp());
        $this->assertStringContainsString('PANGI kembali normal', $this->kirimanWhatsapp()[2]['message']);

        $this->actingAs($this->admin())
            ->get(route('administrasi.server'))
            ->assertSee('Normal')
            ->assertSee('Pulih');
    }

    public function test_peringatan_tetap_terkirim_saat_basis_data_terputus(): void
    {
        $this->simpanPeringatan();

        Http::fake([
            'pangi.test/up' => Http::response('ok', 200),
            'api.fonnte.com/*' => Http::response(['status' => true]),
        ]);

        // Pengaturan tidak lagi dapat dibaca dari basis data maupun cache —
        // pemantau harus bersandar pada cerminnya.
        Cache::flush();
        $semula = config('database.default');
        config([
            'database.connections.terputus' => ['driver' => 'sqlite', 'database' => $this->kerja.'/tidak-ada/pangi.sqlite', 'prefix' => ''],
            'database.default' => 'terputus',
        ]);

        try {
            $this->artisan('pangi:pantau-server')->assertFailed();
            $this->artisan('pangi:pantau-server')->assertFailed();
            $this->artisan('pangi:pantau-server')->assertFailed();
        } finally {
            config(['database.default' => $semula]);
            DB::purge('terputus');
        }

        $this->assertCount(1, $this->kirimanWhatsapp());
        $pesan = $this->kirimanWhatsapp()[0];
        $this->assertSame('6281234567890,6285311112222', $pesan['target']);
        $this->assertStringContainsString('Basis data: Tidak tersambung', $pesan['message']);
        $this->assertTrue($pesan->hasHeader('Authorization', 'TOKENFONNTE123456'));
    }

    public function test_tanpa_pengaturan_gangguan_hanya_dicatat(): void
    {
        Http::fake(['pangi.test/up' => Http::response('galat', 502)]);

        foreach (range(1, 3) as $putaran) {
            $this->artisan('pangi:pantau-server')->assertFailed();
        }

        $this->assertCount(0, $this->kirimanWhatsapp());

        $status = app(PemantauServer::class)->status();
        $this->assertSame(PemantauServer::STATUS_GANGGUAN, $status['status']);
        $this->assertFalse($status['riwayat'][0]['terkirim']);
        $this->assertStringContainsString('nonaktif', $status['riwayat'][0]['keterangan']);
    }
}
