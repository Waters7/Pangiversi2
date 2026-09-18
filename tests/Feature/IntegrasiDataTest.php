<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\LogApi;
use App\Models\Pengaturan;
use App\Models\PengirimanIntegrasi;
use App\Models\User;
use App\Services\PengirimIntegrasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Menu Integrasi Data: pemantauan permintaan API beserta token yang
 * dibawanya, dan pengiriman data dashboard eksekutif terjadwal ke aplikasi
 * tujuan memakai token.
 */
class IntegrasiDataTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-uji-coba-yang-cukup-panjang-untuk-dipakai';

    private const TUJUAN = 'https://dashboard.contoh.test/api/pangi/terima';

    protected function setUp(): void
    {
        parent::setUp();

        config(['api.token' => self::TOKEN]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    private function aktifkanPengiriman(array $lebih = []): void
    {
        // Yang diberikan mengalahkan bawaan: operator + mempertahankan operand kiri.
        Pengaturan::simpan($lebih + [
            Pengaturan::INTEGRASI_AKTIF => '1',
            Pengaturan::INTEGRASI_URL => self::TUJUAN,
            Pengaturan::INTEGRASI_JADWAL => 'harian',
            Pengaturan::INTEGRASI_JAM => '06:00',
        ]);
    }

    // ── Akses ──

    public function test_hanya_super_administrator_yang_membuka_integrasi_data(): void
    {
        $this->actingAs($this->admin())
            ->get(route('administrasi.integrasi'))
            ->assertOk()
            ->assertSee('Integrasi Data')
            ->assertSee('Token API Dashboard Eksekutif')
            ->assertSee('Pemantauan Permintaan API')
            ->assertSee('Pengiriman Data Terjadwal');

        $timSdm = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);
        $this->actingAs($timSdm)->get(route('administrasi.integrasi'))->assertForbidden();
        $this->actingAs($timSdm)->put(route('administrasi.integrasi.jadwal'), [])->assertForbidden();
        $this->actingAs($timSdm)->post(route('administrasi.integrasi.kirim'))->assertForbidden();
    }

    // ── Pemantauan permintaan API ──

    /**
     * Setiap permintaan tercatat — diterima, ditolak, maupun saat API
     * tertutup — dengan token yang dibawa hanya ujung-ujungnya.
     */
    public function test_permintaan_api_tercatat_beserta_token_yang_dibawa(): void
    {
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer token-keliru-yang-panjang'])->assertStatus(401);
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran')->assertStatus(401);

        config(['api.token' => null]);
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['X-Api-Token' => self::TOKEN])->assertStatus(503);

        $this->assertSame(4, LogApi::count());

        $diterima = LogApi::where('hasil', LogApi::DITERIMA)->sole();
        $this->assertSame('/api/v1/dashboard-eksekutif/tahun-anggaran', $diterima->jalur);
        $this->assertSame('GET', $diterima->metode);
        $this->assertSame('token-…akai', $diterima->token_tersamar);
        $this->assertNotNull($diterima->durasi_ms);

        $this->assertSame(2, LogApi::where('hasil', LogApi::DITOLAK)->count());
        $this->assertSame('token-…jang', LogApi::where('hasil', LogApi::DITOLAK)->whereNotNull('token_tersamar')->sole()->token_tersamar);
        $this->assertNull(LogApi::where('hasil', LogApi::DITOLAK)->whereNull('token_tersamar')->sole()->token_tersamar);
        $this->assertSame(1, LogApi::where('hasil', LogApi::TERTUTUP)->count());

        // Token utuh tidak pernah tersimpan.
        $this->assertDatabaseMissing('log_api', ['token_tersamar' => self::TOKEN]);
    }

    public function test_halaman_menampilkan_ringkasan_dan_catatan_permintaan(): void
    {
        $this->getJson('/api/v1/dashboard-eksekutif/tahun-anggaran', ['Authorization' => 'Bearer '.self::TOKEN]);
        $this->getJson('/api/v1/dashboard-eksekutif/ringkasan', ['Authorization' => 'Bearer salah-salah-salah-salah']);

        $halaman = $this->actingAs($this->admin())
            ->get(route('administrasi.integrasi'))
            ->assertOk()
            ->assertSee('token-…akai')
            ->assertSee('salah-…alah')
            ->assertSee('/api/v1/dashboard-eksekutif/ringkasan')
            ->assertSee('Token ditolak')
            ->assertSee('1 ditolak · 7 hari');

        $this->assertSame(2, $halaman->viewData('ringkasanLog')['hari_ini']);
        $this->assertSame(1, $halaman->viewData('ringkasanLog')['ditolak_tujuh_hari']);

        // Saringan hasil membatasi daftarnya.
        $ditolak = $this->actingAs($this->admin())
            ->get(route('administrasi.integrasi', ['hasil' => 'ditolak']))
            ->assertOk();
        $this->assertCount(1, $ditolak->viewData('log'));
        $this->assertSame(LogApi::DITOLAK, $ditolak->viewData('log')->first()->hasil);
    }

    // ── Pengaturan pengiriman ──

    public function test_menyimpan_pengaturan_pengiriman_dengan_token_tujuan_terenkripsi(): void
    {
        $this->actingAs($this->admin())
            ->put(route('administrasi.integrasi.jadwal'), [
                'integrasi_aktif' => '1',
                'integrasi_url' => self::TUJUAN,
                'integrasi_token_tujuan' => 'token-tujuan-rahasia-1234567890',
                'integrasi_jadwal' => 'mingguan',
                'integrasi_jam' => '07:30',
                'integrasi_hari' => '3',
            ])
            ->assertRedirect(route('administrasi.integrasi'))
            ->assertSessionHas('success');

        $pengaturan = app(PengirimIntegrasi::class)->pengaturan();

        $this->assertTrue($pengaturan['aktif']);
        $this->assertSame(self::TUJUAN, $pengaturan['url']);
        $this->assertSame('mingguan', $pengaturan['jadwal']);
        $this->assertSame('07:30', $pengaturan['jam']);
        $this->assertSame('3', $pengaturan['hari']);
        $this->assertSame('sendiri', $pengaturan['token_dari']);
        $this->assertSame('token-tujuan-rahasia-1234567890', app(PengirimIntegrasi::class)->token());
        $this->assertStringNotContainsString('token-tujuan', (string) Pengaturan::query()->where('kunci', Pengaturan::INTEGRASI_TOKEN_TUJUAN)->value('nilai'));
        $this->assertDatabaseHas('audit_logs', ['deskripsi' => 'Pengiriman data terjadwal diaktifkan (Mingguan) ke '.self::TUJUAN.'.']);

        // Kolom token kosong berarti biarkan; centang hapus mengembalikan ke token API.
        $this->actingAs($this->admin())->put(route('administrasi.integrasi.jadwal'), [
            'integrasi_aktif' => '1', 'integrasi_url' => self::TUJUAN, 'integrasi_jadwal' => 'harian', 'integrasi_jam' => '06:00', 'integrasi_hari' => '1',
        ]);
        $this->assertSame('token-tujuan-rahasia-1234567890', app(PengirimIntegrasi::class)->token());

        $this->actingAs($this->admin())->put(route('administrasi.integrasi.jadwal'), [
            'integrasi_aktif' => '1', 'integrasi_url' => self::TUJUAN, 'integrasi_jadwal' => 'harian', 'integrasi_jam' => '06:00', 'integrasi_hari' => '1',
            'hapus_token_tujuan' => '1',
        ]);
        $this->assertSame(self::TOKEN, app(PengirimIntegrasi::class)->token());
        $this->assertSame('api', app(PengirimIntegrasi::class)->pengaturan()['token_dari']);
    }

    public function test_alamat_tujuan_wajib_bila_pengiriman_diaktifkan(): void
    {
        $this->actingAs($this->admin())
            ->from(route('administrasi.integrasi'))
            ->put(route('administrasi.integrasi.jadwal'), [
                'integrasi_aktif' => '1', 'integrasi_url' => '', 'integrasi_jadwal' => 'harian', 'integrasi_jam' => '6 pagi', 'integrasi_hari' => '1',
            ])
            ->assertRedirect(route('administrasi.integrasi'))
            ->assertSessionHasErrors(['integrasi_url', 'integrasi_jam']);

        $this->assertFalse(app(PengirimIntegrasi::class)->siap());
    }

    // ── Jadwal ──

    public function test_jadwal_harian_jatuh_tempo_sekali_pada_jam_kirim(): void
    {
        $this->aktifkanPengiriman();
        $pengirim = app(PengirimIntegrasi::class);

        // Belum pernah mengirim, sebelum jam kirim: berikutnya hari ini 06:00.
        $pagi = Carbon::parse('2026-09-18 05:30');
        $this->assertSame('2026-09-18 06:00', $pengirim->jadwalBerikutnya($pagi)->format('Y-m-d H:i'));
        $this->assertFalse($pengirim->jatuhTempo($pagi));
        $this->assertTrue($pengirim->jatuhTempo(Carbon::parse('2026-09-18 06:00')));

        // Sudah terkirim hari ini: berikutnya besok, tidak diulang tiap menit.
        PengirimanIntegrasi::create([
            'pemicu' => PengirimanIntegrasi::PEMICU_JADWAL, 'tujuan' => self::TUJUAN, 'status' => PengirimanIntegrasi::GAGAL,
            'tahun' => 2026, 'created_at' => Carbon::parse('2026-09-18 06:00:20'),
        ]);
        $this->assertSame('2026-09-19 06:00', $pengirim->jadwalBerikutnya(Carbon::parse('2026-09-18 06:01'))->format('Y-m-d H:i'));
        $this->assertFalse($pengirim->jatuhTempo(Carbon::parse('2026-09-18 23:59')));

        // Pengiriman manual tidak menggeser jadwal.
        PengirimanIntegrasi::create([
            'pemicu' => PengirimanIntegrasi::PEMICU_MANUAL, 'tujuan' => self::TUJUAN, 'status' => PengirimanIntegrasi::BERHASIL,
            'tahun' => 2026, 'created_at' => Carbon::parse('2026-09-19 05:00'),
        ]);
        $this->assertTrue($pengirim->jatuhTempo(Carbon::parse('2026-09-19 06:00')));
    }

    public function test_jadwal_mingguan_dan_tiap_jam(): void
    {
        $this->aktifkanPengiriman([Pengaturan::INTEGRASI_JADWAL => 'mingguan', Pengaturan::INTEGRASI_HARI => '1', Pengaturan::INTEGRASI_JAM => '08:00']);
        $pengirim = app(PengirimIntegrasi::class);

        // Jumat 18 Sep 2026: jadwal Senin 08:00 pekan ini sudah lewat dan belum pernah terkirim → jatuh tempo.
        $this->assertSame('2026-09-14 08:00', $pengirim->jadwalBerikutnya(Carbon::parse('2026-09-18 10:00'))->format('Y-m-d H:i'));
        $this->assertTrue($pengirim->jatuhTempo(Carbon::parse('2026-09-18 10:00')));

        PengirimanIntegrasi::create([
            'pemicu' => PengirimanIntegrasi::PEMICU_JADWAL, 'tujuan' => self::TUJUAN, 'status' => PengirimanIntegrasi::BERHASIL,
            'tahun' => 2026, 'created_at' => Carbon::parse('2026-09-18 10:00:05'),
        ]);
        $this->assertSame('2026-09-21 08:00', $pengirim->jadwalBerikutnya(Carbon::parse('2026-09-18 10:01'))->format('Y-m-d H:i'));

        Pengaturan::simpan([Pengaturan::INTEGRASI_JADWAL => 'tiap_jam']);
        $this->assertSame('2026-09-18 11:00', $pengirim->jadwalBerikutnya(Carbon::parse('2026-09-18 10:30'))->format('Y-m-d H:i'));
        $this->assertFalse($pengirim->jatuhTempo(Carbon::parse('2026-09-18 10:59')));
        $this->assertTrue($pengirim->jatuhTempo(Carbon::parse('2026-09-18 11:00')));

        Pengaturan::simpan([Pengaturan::INTEGRASI_AKTIF => '0']);
        $this->assertNull($pengirim->jadwalBerikutnya());
        $this->assertFalse($pengirim->jatuhTempo());
    }

    // ── Pengiriman ──

    public function test_kirim_sekarang_membawa_token_dan_mencatat_hasilnya(): void
    {
        Http::fake([self::TUJUAN => Http::response(['diterima' => true], 200)]);
        $this->aktifkanPengiriman();

        $this->actingAs($admin = $this->admin())
            ->post(route('administrasi.integrasi.kirim'))
            ->assertRedirect(route('administrasi.integrasi'))
            ->assertSessionHas('success');

        Http::assertSent(function ($permintaan) {
            return $permintaan->url() === self::TUJUAN
                && $permintaan->method() === 'POST'
                && $permintaan->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $permintaan->hasHeader('X-Api-Token', self::TOKEN)
                && $permintaan['sumber'] === 'PANGI'
                && isset($permintaan['data']['ringkasan'], $permintaan['data']['realisasi'], $permintaan['data']['pegawai'])
                && $permintaan['data']['tahun'] === (int) date('Y');
        });

        $catatan = PengirimanIntegrasi::sole();
        $this->assertSame(PengirimanIntegrasi::PEMICU_MANUAL, $catatan->pemicu);
        $this->assertSame($admin->id, $catatan->id_user);
        $this->assertTrue($catatan->berhasil());
        $this->assertSame(200, $catatan->kode_http);
        $this->assertGreaterThan(0, $catatan->ukuran_byte);
        $this->assertDatabaseHas('audit_logs', ['deskripsi' => 'Data dashboard eksekutif dikirim manual ke '.self::TUJUAN.' (HTTP 200).']);

        $this->actingAs($admin)->get(route('administrasi.integrasi'))->assertSee('Berhasil · 200')->assertSee($admin->nama);
    }

    public function test_kegagalan_pengiriman_tercatat_tanpa_melempar_galat(): void
    {
        Http::fake([self::TUJUAN => Http::response('token tidak dikenali', 401)]);
        $this->aktifkanPengiriman();

        $this->actingAs($this->admin())
            ->post(route('administrasi.integrasi.kirim'))
            ->assertSessionHas('error');

        $catatan = PengirimanIntegrasi::sole();
        $this->assertFalse($catatan->berhasil());
        $this->assertSame(401, $catatan->kode_http);
        $this->assertStringContainsString('token tidak dikenali', $catatan->pesan);

        // Tanpa alamat: tercatat gagal tanpa memanggil siapa pun.
        Pengaturan::simpan([Pengaturan::INTEGRASI_URL => '']);
        $this->actingAs($this->admin())->post(route('administrasi.integrasi.kirim'))->assertSessionHas('error');
        $this->assertSame('Alamat tujuan belum diisi atau tidak sah.', PengirimanIntegrasi::latest('id')->first()->pesan);
        Http::assertSentCount(1);
    }

    public function test_perintah_terjadwal_mengirim_hanya_saat_jatuh_tempo(): void
    {
        Http::fake([self::TUJUAN => Http::response('', 202)]);
        $this->aktifkanPengiriman();

        Carbon::setTestNow('2026-09-18 05:00');
        $this->artisan('pangi:kirim-integrasi')->assertSuccessful();
        Http::assertNothingSent();

        Carbon::setTestNow('2026-09-18 06:00');
        $this->artisan('pangi:kirim-integrasi')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame(PengirimanIntegrasi::PEMICU_JADWAL, PengirimanIntegrasi::sole()->pemicu);

        // Menit berikutnya tidak mengirim lagi; --paksa mengirim kapan pun.
        Carbon::setTestNow('2026-09-18 06:01');
        $this->artisan('pangi:kirim-integrasi')->assertSuccessful();
        Http::assertSentCount(1);
        $this->artisan('pangi:kirim-integrasi --paksa')->assertSuccessful();
        Http::assertSentCount(2);

        Carbon::setTestNow();
    }
}
