<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif;
use App\Models\Keuangan;
use App\Models\LogApi;
use App\Models\PengambilanMappalus;
use App\Models\Pengaturan;
use App\Models\RincianBiaya;
use App\Models\TimKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AksesMappalus;
use App\Services\DataMappalus;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Mappalus mengambil daftar nominatif lewat API PANGI memakai token dari
 * PANGI. Hanya pembawa token itu yang dapat mengambil, dan PANGI mencatat data
 * apa saja yang sudah diambil dan dikonfirmasi.
 */
class IntegrasiMappalusTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/99/2026';

    private User $admin;

    private User $ppk;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $this->ppk = User::factory()->ppk()->create();
        $this->token = app(AksesMappalus::class)->buatToken();
    }

    private function pelaksanaSelesai(string $nama = 'Maya Sari'): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => User::factory()->create(['nama' => $nama])->id,
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        $usulan = $this->lengkapiPertanggungjawaban($usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($usulan);
        $this->tandatanganiBerkas($usulan->fresh(), $this->ppk);

        return $usulan->fresh();
    }

    private function nominatifBertandaTangan(): DaftarNominatif
    {
        $daftar = app(PenyusunNominatif::class)->terbitkan(self::NO_TUGAS);

        $daftar->update([
            'id_ppk' => $this->ppk->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
            'id_akun_pembiayaan' => AkunPembiayaan::firstWhere('kode', '524111')->id,
            'id_tim_kerja' => TimKerja::factory()->create(['nama' => 'Tim OSDM'])->id,
        ]);

        return $daftar;
    }

    /** @param  array<string, mixed>  $parameter */
    private function ambil(array $parameter = [], ?string $token = null): TestResponse
    {
        return $this->withToken($token ?? $this->token)->getJson(route('api.v1.mappalus.nominatif', $parameter));
    }

    /** @param  array<int, array<string, mixed>>  $baris */
    private function konfirmasi(array $baris): TestResponse
    {
        return $this->withToken($this->token)->postJson(route('api.v1.mappalus.nominatif.konfirmasi'), [
            'data' => array_map(fn (array $isi) => ['referensi' => $isi['referensi'], 'versi' => $isi['versi']], $baris),
        ]);
    }

    /** @param  array<string, mixed>  $saringan */
    private function isiEkspor(array $saringan = []): string
    {
        $balasan = $this->actingAs($this->admin)->get(route('administrasi.mappalus.ekspor', $saringan))->assertOk();

        $berkas = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
        file_put_contents($berkas, $balasan->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas) === true);

        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        $zip->close();
        unlink($berkas);

        return $xml;
    }

    /** @return array<string, string> */
    private function statusPerReferensi(): array
    {
        return app(DataMappalus::class)->baris()->pluck('status', 'data.referensi')->all();
    }

    // ── Keamanan ──

    public function test_permintaan_tanpa_token_atau_token_salah_ditolak_dan_tercatat(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        $this->getJson(route('api.v1.mappalus.nominatif'))->assertUnauthorized()->assertExactJson(['message' => 'Akses ditolak.']);
        $this->ambil(token: 'token-tebakan-yang-salah')->assertUnauthorized();
        $this->postJson(route('api.v1.mappalus.nominatif.konfirmasi'), ['data' => []])->assertUnauthorized();

        $this->assertSame(3, LogApi::where('hasil', LogApi::DITOLAK)->count());
        $this->assertSame(0, PengambilanMappalus::count());
    }

    public function test_api_tertutup_selama_belum_ada_token(): void
    {
        app(AksesMappalus::class)->cabutToken();

        $this->ambil()->assertUnauthorized();
        $this->withToken('')->getJson(route('api.v1.mappalus.nominatif'))->assertUnauthorized();

        $this->assertSame(2, LogApi::where('hasil', LogApi::TERTUTUP)->count());
    }

    public function test_token_hanya_diterima_lewat_header_authorization(): void
    {
        $this->getJson(route('api.v1.mappalus.nominatif', ['token' => $this->token]))->assertUnauthorized();
        $this->getJson(route('api.v1.mappalus.nominatif'), ['X-Api-Token' => $this->token])->assertUnauthorized();
    }

    public function test_token_mappalus_dan_token_dashboard_tidak_saling_membuka(): void
    {
        config(['api.token' => 'token-dashboard-eksekutif']);

        $this->ambil(token: 'token-dashboard-eksekutif')->assertUnauthorized();
        $this->withToken($this->token)->getJson('/api/v1/dashboard-eksekutif/ringkasan')->assertUnauthorized();
    }

    public function test_token_tersimpan_sebagai_sidik_dan_tampil_sekali(): void
    {
        $tersimpan = DB::table('pengaturan')->pluck('nilai')->implode(' ');
        $this->assertStringNotContainsString($this->token, $tersimpan);

        $this->actingAs($this->admin)->followingRedirects()
            ->post(route('administrasi.mappalus.token.buat'))
            ->assertOk()
            ->assertSee('data-token-baru', false);

        $this->actingAs($this->admin)->get(route('administrasi.mappalus'))
            ->assertOk()
            ->assertDontSee('data-token-baru', false)
            ->assertSee('Token berakhiran');
    }

    public function test_token_baru_menggugurkan_yang_lama_dan_pencabutan_menutup_api(): void
    {
        $this->ambil()->assertOk();

        $baru = app(AksesMappalus::class)->buatToken();

        $this->ambil()->assertUnauthorized();
        $this->ambil(token: $baru)->assertOk();

        $this->actingAs($this->admin)->delete(route('administrasi.mappalus.token.cabut'))->assertSessionHas('success');

        $this->ambil(token: $baru)->assertUnauthorized();
    }

    public function test_batas_alamat_menolak_pemanggil_lain_walau_tokennya_benar(): void
    {
        $this->actingAs($this->admin)
            ->put(route('administrasi.mappalus.alamat'), ['alamat_ip' => "203.0.113.10\n198.51.100.0/24"])
            ->assertSessionHasNoErrors();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99']);
        $this->ambil()->assertUnauthorized();
        $this->assertSame(1, LogApi::where('hasil', LogApi::ALAMAT_DITOLAK)->count());

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);
        $this->ambil()->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);
        $this->ambil()->assertOk();
    }

    public function test_batas_alamat_menolak_isian_yang_bukan_alamat(): void
    {
        foreach (['bukan-alamat', '10.0.0.1/99', '10.0.0.999'] as $isian) {
            $this->actingAs($this->admin)
                ->put(route('administrasi.mappalus.alamat'), ['alamat_ip' => $isian])
                ->assertSessionHasErrors('alamat.0');
        }

        $this->assertSame([], app(AksesMappalus::class)->daftarAlamat());
    }

    public function test_produksi_menolak_permintaan_tanpa_https(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->withToken($this->token)->getJson('http://pangi.test/api/v1/mappalus/nominatif')->assertUnauthorized();
        $this->withToken($this->token)->getJson('https://pangi.test/api/v1/mappalus/nominatif')->assertOk();
    }

    public function test_permintaan_berlebihan_dibatasi_termasuk_tebakan_token(): void
    {
        foreach (range(1, 30) as $tebakan) {
            $this->ambil(token: "tebakan-{$tebakan}")->assertUnauthorized();
        }

        $this->ambil()->assertTooManyRequests();
    }

    public function test_peramban_dari_asal_lain_tidak_dapat_membaca_balasan(): void
    {
        config(['cors.allowed_origins' => ['https://dashboard.contoh.test']]);

        $this->withToken($this->token)
            ->getJson(route('api.v1.mappalus.nominatif'), ['Origin' => 'https://dashboard.contoh.test'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_balasan_hanya_memuat_data_yang_diminta_dan_tidak_tersimpan_di_tembolok(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        $balasan = $this->ambil()->assertOk();

        $this->assertStringContainsString('no-store', (string) $balasan->headers->get('Cache-Control'));
        $this->assertSame(
            ['referensi', 'tim_kerja', 'kode_akun', 'total_nominal', 'keterangan', 'versi', 'status'],
            array_keys($balasan->json('data.0')),
        );
        $this->assertStringNotContainsString((string) $usulan->user->nip, $balasan->getContent());
    }

    public function test_hanya_super_administrator_yang_mengelola(): void
    {
        $bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);

        $this->actingAs($bendahara)->get(route('administrasi.mappalus'))->assertForbidden();
        $this->actingAs($bendahara)->post(route('administrasi.mappalus.token.buat'))->assertForbidden();
        $this->actingAs($bendahara)->delete(route('administrasi.mappalus.token.cabut'))->assertForbidden();
        $this->actingAs($bendahara)->put(route('administrasi.mappalus.alamat'), ['alamat_ip' => '10.0.0.1'])->assertForbidden();

        $this->assertTrue(app(AksesMappalus::class)->tokenCocok($this->token));
    }

    // ── Data yang tersedia ──

    public function test_tiap_pelaksana_tersaji_tersendiri_dengan_data_yang_diminta(): void
    {
        $maya = $this->pelaksanaSelesai('Maya Sari');
        $rina = $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();

        $jumlah = app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->pluck('jumlah', 'nama');

        $this->ambil()
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment([
                'referensi' => $maya->no_usulan,
                'tim_kerja' => 'Tim OSDM',
                'kode_akun' => '524111',
                'total_nominal' => round($jumlah['Maya Sari'], 2),
                'keterangan' => 'Maya Sari - '.self::NO_TUGAS,
                'status' => 'baru',
            ])
            ->assertJsonFragment(['referensi' => $rina->no_usulan, 'keterangan' => 'Rina Kartika - '.self::NO_TUGAS]);
    }

    public function test_tim_kerja_dan_kode_akun_yang_kosong_tidak_tersedia(): void
    {
        $this->pelaksanaSelesai();
        $daftar = $this->nominatifBertandaTangan();

        foreach ([['id_tim_kerja' => null], ['id_akun_pembiayaan' => null]] as $kosong) {
            $isian = $daftar->only('id_tim_kerja', 'id_akun_pembiayaan');
            $daftar->update($kosong);

            $this->ambil()->assertOk()->assertJsonCount(0, 'data');

            $daftar->update($isian);
        }

        $this->ambil()->assertJsonCount(1, 'data');
    }

    public function test_nominatif_yang_belum_ditandatangani_tidak_tersedia(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan()->update(['ditandatangani_at' => null]);

        $this->ambil()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pelaksana_yang_belum_selesai_tidak_tersedia(): void
    {
        $selesai = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        Usulan::factory()->create(['no_tugas' => self::NO_TUGAS, 'status' => StatusUsulan::Disetujui->value]);

        $this->ambil()->assertJsonCount(1, 'data')->assertJsonPath('data.0.referensi', $selesai->no_usulan);
    }

    public function test_data_terbagi_per_halaman(): void
    {
        $this->pelaksanaSelesai('Maya Sari');
        $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();

        $this->ambil(['per_halaman' => 1])->assertJsonCount(1, 'data')->assertJsonPath('meta.halaman_terakhir', 2);
        $this->ambil(['per_halaman' => 1, 'halaman' => 2])->assertJsonCount(1, 'data');
        $this->ambil(['per_halaman' => 1, 'halaman' => 3])->assertJsonCount(0, 'data');
    }

    // ── Pencatatan pengambilan ──

    public function test_pengambilan_tercatat_dan_data_tetap_tersaji_sampai_dikonfirmasi(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        $this->assertSame([$usulan->no_usulan => DataMappalus::BELUM_DIAMBIL], $this->statusPerReferensi());

        $baris = $this->ambil()->json('data');
        $this->ambil()->assertJsonCount(1, 'data');

        $catatan = PengambilanMappalus::sole();

        $this->assertSame(2, $catatan->jumlah_diambil);
        $this->assertNotNull($catatan->pertama_diambil_at);
        $this->assertSame([$usulan->no_usulan => DataMappalus::SUDAH_DIAMBIL], $this->statusPerReferensi());

        $this->konfirmasi($baris)
            ->assertOk()
            ->assertExactJson(['dikonfirmasi' => [$usulan->no_usulan], 'ditolak' => []]);

        $this->assertSame([$usulan->no_usulan => DataMappalus::DIKONFIRMASI], $this->statusPerReferensi());
        $this->assertNotNull($catatan->fresh()->dikonfirmasi_at);

        $this->ambil()->assertJsonCount(0, 'data');
        $this->assertSame(2, $catatan->fresh()->jumlah_diambil);
    }

    public function test_konfirmasi_ulang_tidak_mengubah_apa_pun(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        $baris = $this->ambil()->json('data');
        $this->konfirmasi($baris);
        $waktu = PengambilanMappalus::sole()->dikonfirmasi_at;

        $this->travel(1)->hours();
        $this->konfirmasi($baris)->assertOk();

        $this->assertEquals($waktu, PengambilanMappalus::sole()->dikonfirmasi_at);
    }

    public function test_konfirmasi_menolak_referensi_asing_dan_versi_lama(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        $lama = $this->ambil()->json('data');
        RincianBiaya::whereHas('keuangan', fn ($q) => $q->where('id_usulan', $usulan->id))->first()->increment('jumlah', 50_000);
        $this->ambil();

        $this->konfirmasi([...$lama, ['referensi' => 'PJ-ASING', 'versi' => str_repeat('a', 64)]])
            ->assertOk()
            ->assertExactJson(['dikonfirmasi' => [], 'ditolak' => [$usulan->no_usulan, 'PJ-ASING']]);

        $this->assertFalse(PengambilanMappalus::sole()->pernahDikonfirmasi());
    }

    public function test_konfirmasi_memeriksa_bentuk_kiriman(): void
    {
        $tanpaAccept = fn (array $isi) => $this->withToken($this->token)
            ->post(route('api.v1.mappalus.nominatif.konfirmasi'), $isi, ['Accept' => '*/*']);

        $tanpaAccept([])->assertUnprocessable()->assertJsonValidationErrors('data');
        $tanpaAccept(['data' => [['referensi' => 'PJ-1', 'versi' => 'pendek']]])->assertUnprocessable();
        $tanpaAccept(['data' => array_fill(0, 201, ['referensi' => 'PJ-1', 'versi' => str_repeat('a', 64)])])->assertUnprocessable();
    }

    public function test_data_yang_berubah_sesudah_dikonfirmasi_tersaji_lagi_tanpa_catatan_ganda(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        $this->konfirmasi($this->ambil()->json('data'));

        RincianBiaya::whereHas('keuangan', fn ($q) => $q->where('id_usulan', $usulan->id))->first()->increment('jumlah', 50_000);

        $this->assertSame([$usulan->no_usulan => DataMappalus::BERUBAH], $this->statusPerReferensi());

        $baris = $this->ambil()->assertJsonPath('data.0.status', 'berubah')->json('data');

        $this->konfirmasi($baris)->assertJsonPath('dikonfirmasi.0', $usulan->no_usulan);

        $this->assertSame(1, PengambilanMappalus::count());
        $this->assertSame([$usulan->no_usulan => DataMappalus::DIKONFIRMASI], $this->statusPerReferensi());
    }

    public function test_tanda_tangan_dicabut_mengabarkan_data_ditarik_dan_memperingatkan_ppk(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $daftar = $this->nominatifBertandaTangan();

        $this->konfirmasi($this->ambil()->json('data'));

        $this->actingAs($this->ppk)
            ->delete(route('persetujuan.nominatif.batal-tanda-tangan', $daftar), ['alasan' => 'Nominal uang harian keliru.'])
            ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, '1 data pelaksananya sudah diambil Mappalus'));

        $this->assertSame([$usulan->no_usulan => DataMappalus::DITARIK], $this->statusPerReferensi());
        $this->ambil()->assertJsonCount(0, 'data')->assertJsonPath('ditarik', [$usulan->no_usulan]);

        $this->actingAs($this->ppk)->put(route('persetujuan.nominatif.tanda-tangan', $daftar));

        $this->assertSame([$usulan->no_usulan => DataMappalus::DIKONFIRMASI], $this->statusPerReferensi());
        $this->ambil()->assertJsonPath('ditarik', []);
    }

    // ── Kirim ulang menurut waktu kirim ──

    /** @param  array<string, mixed>  $rentang */
    private function mintaUlang(array $rentang): TestResponse
    {
        return $this->withToken($this->token)->getJson(route('api.v1.mappalus.nominatif.terkirim', $rentang));
    }

    /** Dua pelaksana yang terkirim pada hari berbeda, lalu dikonfirmasi. */
    private function terkirimDuaHari(): array
    {
        $maya = $this->pelaksanaSelesai('Maya Sari');
        $this->nominatifBertandaTangan();

        $this->travelTo('2026-11-03 10:00:00');
        $this->konfirmasi($this->ambil()->json('data'));

        $rina = $this->pelaksanaSelesai('Rina Kartika');

        $this->travelTo('2026-11-05 09:00:00');
        $this->konfirmasi($this->ambil()->json('data'));

        return [$maya, $rina];
    }

    public function test_data_terkirim_dapat_diminta_lagi_menurut_waktu_kirimnya(): void
    {
        [$maya, $rina] = $this->terkirimDuaHari();

        $this->ambil()->assertJsonCount(0, 'data');

        $this->mintaUlang(['dari' => '2026-11-03', 'sampai' => '2026-11-03'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referensi', $maya->no_usulan)
            ->assertJsonPath('data.0.status', DataMappalus::DIKONFIRMASI)
            ->assertJsonPath('data.0.keterangan', 'Maya Sari - '.self::NO_TUGAS)
            ->assertJsonPath('meta.total', 1);

        $semua = $this->mintaUlang(['dari' => '2026-11-01', 'sampai' => '2026-11-30'])->assertJsonCount(2, 'data');

        $this->assertSame([$maya->no_usulan, $rina->no_usulan], array_column($semua->json('data'), 'referensi'));

        $this->mintaUlang(['dari' => '2026-11-04', 'sampai' => '2026-11-04'])->assertJsonCount(0, 'data');
    }

    public function test_rentang_memakai_jam_bila_disebut(): void
    {
        $this->terkirimDuaHari();

        $this->mintaUlang(['dari' => '2026-11-03 09:00:00', 'sampai' => '2026-11-03 09:59:59'])->assertJsonCount(0, 'data');
        $this->mintaUlang(['dari' => '2026-11-03 09:00:00', 'sampai' => '2026-11-03 10:00:00'])->assertJsonCount(1, 'data');
        $this->mintaUlang(['dari' => '2026-11-03T17:30:00+08:00', 'sampai' => '2026-11-03T18:30:00+08:00'])->assertJsonCount(1, 'data');
    }

    /** Rentang yang sama harus selalu menyajikan data yang sama, dan statusnya tidak berubah. */
    public function test_permintaan_ulang_tidak_mengubah_waktu_kirim_maupun_konfirmasi(): void
    {
        [$maya] = $this->terkirimDuaHari();
        $catatan = PengambilanMappalus::firstWhere('referensi', $maya->no_usulan);

        $this->travelTo('2026-11-06 08:00:00');
        $this->mintaUlang(['dari' => '2026-11-03', 'sampai' => '2026-11-03'])->assertJsonCount(1, 'data');
        $this->mintaUlang(['dari' => '2026-11-03', 'sampai' => '2026-11-03'])->assertJsonCount(1, 'data');

        $sesudah = $catatan->fresh();

        $this->assertEquals($catatan->diambil_at, $sesudah->diambil_at);
        $this->assertEquals($catatan->dikonfirmasi_at, $sesudah->dikonfirmasi_at);
        $this->assertSame($catatan->jumlah_diambil, $sesudah->jumlah_diambil);
        $this->assertSame(2, $sesudah->jumlah_kirim_ulang);
        $this->assertEquals(now(), $sesudah->kirim_ulang_at);
        $this->assertSame(DataMappalus::DIKONFIRMASI, $this->statusPerReferensi()[$maya->no_usulan]);
    }

    public function test_permintaan_ulang_menyajikan_versi_yang_dulu_dikirim(): void
    {
        [$maya] = $this->terkirimDuaHari();
        $catatan = PengambilanMappalus::firstWhere('referensi', $maya->no_usulan);

        RincianBiaya::whereHas('keuangan', fn ($q) => $q->where('id_usulan', $maya->id))->first()->increment('jumlah', 50_000);

        $this->mintaUlang(['dari' => '2026-11-03', 'sampai' => '2026-11-03'])
            ->assertJsonPath('data.0.versi', $catatan->sidik)
            ->assertJsonPath('data.0.total_nominal', fn ($nilai) => (float) $nilai === (float) $catatan->total_nominal)
            ->assertJsonPath('data.0.status', DataMappalus::BERUBAH);
    }

    public function test_data_yang_ditarik_tidak_dikirim_ulang_hanya_disebut(): void
    {
        [$maya] = $this->terkirimDuaHari();

        DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS)->update(['ditandatangani_at' => null]);

        $this->mintaUlang(['dari' => '2026-11-03', 'sampai' => '2026-11-03'])
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('ditarik', [$maya->no_usulan]);

        $this->assertSame(0, PengambilanMappalus::firstWhere('referensi', $maya->no_usulan)->jumlah_kirim_ulang);
    }

    public function test_permintaan_ulang_terbagi_per_halaman(): void
    {
        $this->terkirimDuaHari();

        $rentang = ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'per_halaman' => 1];

        $this->mintaUlang($rentang)->assertJsonCount(1, 'data')->assertJsonPath('meta.halaman_terakhir', 2);
        $this->mintaUlang($rentang + ['halaman' => 2])->assertJsonCount(1, 'data');

        $this->assertSame(2, (int) PengambilanMappalus::sum('jumlah_kirim_ulang'));
    }

    public function test_permintaan_ulang_memeriksa_rentang_dan_token(): void
    {
        $this->getJson(route('api.v1.mappalus.nominatif.terkirim', ['dari' => '2026-11-01', 'sampai' => '2026-11-05']))->assertUnauthorized();

        $this->mintaUlang([])->assertUnprocessable()->assertJsonValidationErrors(['dari', 'sampai']);
        $this->mintaUlang(['dari' => 'kemarin-lusa', 'sampai' => '2026-11-05'])->assertUnprocessable()->assertJsonValidationErrors('dari');
        $this->mintaUlang(['dari' => '2026-11-05', 'sampai' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('sampai');
    }

    public function test_halaman_dan_ekspor_dapat_disaring_menurut_waktu_kirim(): void
    {
        [$maya, $rina] = $this->terkirimDuaHari();

        $this->mintaUlang(['dari' => '2026-11-03', 'sampai' => '2026-11-03']);

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus', ['dari' => '2026-11-03', 'sampai' => '2026-11-03']))
            ->assertSee('Maya Sari - '.self::NO_TUGAS)
            ->assertDontSee('Rina Kartika - '.self::NO_TUGAS)
            ->assertSee('1× dikirim ulang');

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus', ['dari' => 'bukan-tanggal']))
            ->assertOk()
            ->assertSee('Rina Kartika - '.self::NO_TUGAS);

        $ekspor = $this->isiEkspor(['dari' => '2026-11-05', 'sampai' => '2026-11-05']);

        $this->assertStringContainsString($rina->no_usulan, $ekspor);
        $this->assertStringNotContainsString($maya->no_usulan, $ekspor);
    }

    // ── Halaman ──

    public function test_halaman_menunjukkan_data_yang_sudah_diambil_dan_dapat_disaring(): void
    {
        $maya = $this->pelaksanaSelesai('Maya Sari');
        $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();

        $baris = collect($this->ambil()->json('data'));
        $this->konfirmasi($baris->where('referensi', $maya->no_usulan)->values()->all());

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus'))
            ->assertSee('data-baris-mappalus="dikonfirmasi"', false)
            ->assertSee('data-baris-mappalus="sudah_diambil"', false)
            ->assertSee('1× diambil')
            ->assertSee('/api/v1/mappalus/nominatif');

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus', ['status' => DataMappalus::SUDAH_DIAMBIL]))
            ->assertDontSee('data-baris-mappalus="dikonfirmasi"', false)
            ->assertSee('Rina Kartika - '.self::NO_TUGAS);

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus', ['cari' => $maya->no_usulan]))
            ->assertSee('Maya Sari - '.self::NO_TUGAS)
            ->assertDontSee('Rina Kartika - '.self::NO_TUGAS);
    }

    public function test_halaman_memisahkan_yang_belum_bertim_kerja_atau_berakun(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan()->update(['id_tim_kerja' => null]);

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus'))
            ->assertSee('1 pelaksana belum ikut karena tim kerja atau kode akunnya belum diisi.')
            ->assertSee(route('pembebanan-anggaran'))
            ->assertDontSee('data-baris-mappalus', false);
    }

    public function test_ekspor_excel_memuat_seluruh_saringan_dan_catatan_pengambilan(): void
    {
        $maya = $this->pelaksanaSelesai('Maya Sari');
        $rina = $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();

        $baris = collect($this->ambil()->json('data'));
        $this->konfirmasi($baris->where('referensi', $maya->no_usulan)->values()->all());

        $semua = $this->isiEkspor();

        foreach (['DATA INTEGRASI MAPPALUS', 'Maya Sari - '.self::NO_TUGAS, 'Rina Kartika - '.self::NO_TUGAS, 'Tim OSDM', '524111', 'Dikonfirmasi', 'Sudah Diambil'] as $teks) {
            $this->assertStringContainsString($teks, $semua);
        }

        $dikonfirmasi = $this->isiEkspor(['status' => DataMappalus::DIKONFIRMASI]);

        $this->assertStringContainsString($maya->no_usulan, $dikonfirmasi);
        $this->assertStringNotContainsString($rina->no_usulan, $dikonfirmasi);
    }

    public function test_ekspor_excel_hanya_untuk_super_administrator(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_BENDAHARA]))
            ->get(route('administrasi.mappalus.ekspor'))
            ->assertForbidden();
    }

    public function test_riwayat_memuat_permintaan_yang_ditolak_tanpa_token_utuh(): void
    {
        $this->ambil(token: 'token-tebakan-yang-cukup-panjang')->assertUnauthorized();

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus'))
            ->assertSee('1 ditolak · 7 hari')
            ->assertSee('Token ditolak')
            ->assertDontSee('token-tebakan-yang-cukup-panjang');
    }

    public function test_pengaturan_lama_tidak_lagi_dipakai(): void
    {
        $this->assertFalse(defined(Pengaturan::class.'::MAPPALUS_URL'));
    }
}
