<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif;
use App\Models\Keuangan;
use App\Models\Pengaturan;
use App\Models\PengirimanMappalus;
use App\Models\RincianBiaya;
use App\Models\TimKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use App\Services\SinkronBiayaDokumen;
use App\Services\SinkronMappalus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Daftar nominatif bertanda tangan PPK dikirim ke Mappalus per pelaksana,
 * dan statusnya membedakan yang sudah diterima dari yang gagal supaya tidak
 * ada data ganda maupun kiriman yang terlewat.
 */
class IntegrasiMappalusTest extends TestCase
{
    use RefreshDatabase;

    private const ALAMAT = 'https://mappalus.contoh.test/api/nominatif';

    private const TOKEN = 'token-uji-mappalus';

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/99/2026';

    private User $admin;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $this->ppk = User::factory()->ppk()->create();

        Pengaturan::simpan([Pengaturan::MAPPALUS_URL => self::ALAMAT]);
        Pengaturan::simpanRahasia(Pengaturan::MAPPALUS_TOKEN, self::TOKEN);
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

    private function mappalusMenerima(): void
    {
        Http::fake([self::ALAMAT => Http::response(['diterima' => true], 201)]);
    }

    /** @return array<string, string> */
    private function statusPerReferensi(): array
    {
        return app(SinkronMappalus::class)->baris()->pluck('status', 'data.referensi')->all();
    }

    private function sinkronManual(): mixed
    {
        return $this->actingAs($this->admin)->post(route('administrasi.mappalus.sinkron'));
    }

    public function test_hanya_super_administrator_yang_dapat_membuka_dan_mengirim(): void
    {
        $bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);

        $this->actingAs($bendahara)->get(route('administrasi.mappalus'))->assertForbidden();
        $this->actingAs($bendahara)->post(route('administrasi.mappalus.sinkron'))->assertForbidden();
        $this->actingAs($bendahara)->post(route('administrasi.mappalus.kirim-ulang', 'PJ-1'))->assertForbidden();
        $this->actingAs($bendahara)->put(route('administrasi.mappalus.pengaturan'), ['mappalus_token' => 'lain'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('administrasi.mappalus'))->assertOk()->assertSee('Integrasi Mappalus');
    }

    public function test_token_tersimpan_terenkripsi_dan_kolom_kosong_membiarkannya(): void
    {
        $simpan = fn (array $isian) => $this->actingAs($this->admin)
            ->put(route('administrasi.mappalus.pengaturan'), ['mappalus_url' => self::ALAMAT] + $isian)
            ->assertSessionHasNoErrors();

        $simpan(['mappalus_token' => 'token-baru-dari-pengembang']);

        $this->assertStringNotContainsString('token-baru-dari-pengembang', (string) DB::table('pengaturan')->where('kunci', Pengaturan::MAPPALUS_TOKEN)->value('nilai'));
        $this->assertSame('token-baru-dari-pengembang', app(SinkronMappalus::class)->token());

        $simpan([]);
        $this->assertSame('token-baru-dari-pengembang', app(SinkronMappalus::class)->token());

        $this->actingAs($this->admin)->get(route('administrasi.mappalus'))->assertDontSee('token-baru-dari-pengembang');

        $simpan(['hapus_mappalus_token' => '1']);
        $this->assertSame('', app(SinkronMappalus::class)->token());
    }

    public function test_pengiriman_otomatis_membutuhkan_alamat(): void
    {
        $this->actingAs($this->admin)
            ->put(route('administrasi.mappalus.pengaturan'), ['mappalus_aktif' => '1', 'mappalus_url' => ''])
            ->assertSessionHasErrors('mappalus_url');
    }

    public function test_tiap_pelaksana_dikirim_tersendiri_dengan_data_yang_diminta(): void
    {
        $maya = $this->pelaksanaSelesai('Maya Sari');
        $rina = $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();
        $this->mappalusMenerima();

        $this->sinkronManual()->assertSessionHas('success', 'Sinkron selesai: 2 data berhasil, 0 gagal.');

        $jumlah = app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->pluck('jumlah', 'nama');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $kiriman) => $kiriman->url() === self::ALAMAT
            && $kiriman->method() === 'POST'
            && $kiriman->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && $kiriman->hasHeader('X-Api-Token', self::TOKEN)
            && $kiriman->data() === [
                'referensi' => $maya->no_usulan,
                'tim_kerja' => 'Tim OSDM',
                'kode_akun' => '524111',
                'total_nominal' => round($jumlah['Maya Sari'], 2),
                'keterangan' => 'Maya Sari - '.self::NO_TUGAS,
            ]);
        Http::assertSent(fn (Request $kiriman) => $kiriman['referensi'] === $rina->no_usulan
            && $kiriman['keterangan'] === 'Rina Kartika - '.self::NO_TUGAS);

        $this->assertSame([SinkronMappalus::BERHASIL], array_values(array_unique($this->statusPerReferensi())));
    }

    public function test_nominatif_belum_bertanda_tangan_tidak_ikut(): void
    {
        $this->pelaksanaSelesai();
        app(PenyusunNominatif::class)->terbitkan(self::NO_TUGAS);

        $this->assertSame([], $this->statusPerReferensi());
    }

    public function test_pelaksana_yang_belum_selesai_tidak_ikut(): void
    {
        $selesai = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->assertSame([$selesai->no_usulan], array_keys($this->statusPerReferensi()));
    }

    public function test_yang_belum_berakun_atau_bertim_tidak_dikirim(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan()->update(['id_tim_kerja' => null]);
        Http::fake();

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::BELUM_LENGKAP], $this->statusPerReferensi());

        app(SinkronMappalus::class)->sinkron();

        Http::assertNothingSent();
    }

    /** Pencegah data ganda: yang sudah diterima tidak terkirim lagi, oleh siapa pun. */
    public function test_data_yang_sudah_diterima_tidak_terkirim_dua_kali(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();
        $this->mappalusMenerima();

        app(SinkronMappalus::class)->sinkron($this->admin);
        app(SinkronMappalus::class)->sinkron($this->admin);
        $this->artisan('pangi:sinkron-mappalus');

        Http::assertSentCount(1);
        $this->assertSame(1, PengirimanMappalus::count());
    }

    public function test_kiriman_ditolak_tercatat_gagal_lalu_berhasil_saat_diulang(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        Http::fakeSequence(self::ALAMAT)
            ->push(['pesan' => 'token salah'], 401)
            ->push(['diterima' => true], 200);

        $this->sinkronManual()->assertSessionHas('error', 'Sinkron selesai: 0 data berhasil, 1 gagal.');

        $catatan = PengirimanMappalus::firstWhere('referensi', $usulan->no_usulan);

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::GAGAL], $this->statusPerReferensi());
        $this->assertSame(401, $catatan->kode_http);
        $this->assertStringContainsString('token salah', $catatan->pesan);

        $this->sinkronManual()->assertSessionHas('success');

        $catatan->refresh();

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::BERHASIL], $this->statusPerReferensi());
        $this->assertSame(2, $catatan->percobaan);
        $this->assertNull($catatan->pesan);
        $this->assertNotNull($catatan->diterima_at);
    }

    /** Mappalus berupa aplikasi web yang menjawab 200 pada alamat apa pun. */
    public function test_balasan_200_berupa_halaman_web_bukan_bukti_diterima(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();

        Http::fake([self::ALAMAT => Http::response('<!doctype html><title>Streamlit</title>', 200, ['Content-Type' => 'text/html'])]);

        app(SinkronMappalus::class)->sinkron();

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::GAGAL], $this->statusPerReferensi());
        $this->assertStringContainsString('bukan JSON', PengirimanMappalus::sole()->pesan);
    }

    public function test_sambungan_putus_menghentikan_antrean_tanpa_menandai_sisanya(): void
    {
        $this->pelaksanaSelesai('Maya Sari');
        $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();

        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $hasil = app(SinkronMappalus::class)->sinkron();

        $this->assertSame(['berhasil' => 0, 'gagal' => 1, 'sisa' => 1], $hasil);
        $this->assertSame(1, PengirimanMappalus::count());
        $this->assertEqualsCanonicalizing(
            [SinkronMappalus::GAGAL, SinkronMappalus::SIAP],
            array_values($this->statusPerReferensi()),
        );
    }

    public function test_data_yang_berubah_tidak_terkirim_sendiri_dan_dapat_dikirim_ulang(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();
        $this->mappalusMenerima();

        app(SinkronMappalus::class)->sinkron();

        RincianBiaya::whereHas('keuangan', fn ($q) => $q->where('id_usulan', $usulan->id))->first()->increment('jumlah', 50_000);

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::BERUBAH], $this->statusPerReferensi());

        app(SinkronMappalus::class)->sinkron();
        Http::assertSentCount(1);

        $this->actingAs($this->admin)
            ->post(route('administrasi.mappalus.kirim-ulang', $usulan->no_usulan))
            ->assertSessionHas('success', "Data {$usulan->no_usulan} diterima Mappalus.");

        Http::assertSentCount(2);
        $this->assertSame([$usulan->no_usulan => SinkronMappalus::BERHASIL], $this->statusPerReferensi());
        $this->assertSame(1, PengirimanMappalus::count());
    }

    public function test_data_yang_sudah_berhasil_tidak_dapat_dikirim_ulang(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();
        $this->mappalusMenerima();

        app(SinkronMappalus::class)->sinkron();

        $this->actingAs($this->admin)
            ->post(route('administrasi.mappalus.kirim-ulang', $usulan->no_usulan))
            ->assertSessionHas('error', "Data {$usulan->no_usulan} tidak perlu dikirim ulang.");

        Http::assertSentCount(1);
    }

    public function test_tanda_tangan_dicabut_menandai_data_ditarik_dan_memperingatkan_ppk(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $daftar = $this->nominatifBertandaTangan();
        $this->mappalusMenerima();

        app(SinkronMappalus::class)->sinkron();

        $this->actingAs($this->ppk)
            ->delete(route('persetujuan.nominatif.batal-tanda-tangan', $daftar), ['alasan' => 'Nominal uang harian keliru.'])
            ->assertSessionHas('success', fn (string $pesan) => str_contains($pesan, '1 data pelaksananya sudah diterima Mappalus'));

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::DITARIK], $this->statusPerReferensi());

        $this->actingAs($this->ppk)->put(route('persetujuan.nominatif.tanda-tangan', $daftar));

        $this->assertSame([$usulan->no_usulan => SinkronMappalus::BERHASIL], $this->statusPerReferensi());
    }

    public function test_perintah_terjadwal_diam_selama_tidak_diaktifkan(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();
        Http::fake();

        $this->artisan('pangi:sinkron-mappalus')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_pengiriman_otomatis_berhenti_pada_batas_percobaan_tetapi_manual_tetap_jalan(): void
    {
        $usulan = $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();
        Pengaturan::simpan([Pengaturan::MAPPALUS_AKTIF => '1']);

        Http::fake([self::ALAMAT => Http::response(['pesan' => 'sedang perawatan'], 503)]);

        foreach (range(1, SinkronMappalus::MAKS_PERCOBAAN_OTOMATIS + 2) as $putaran) {
            $this->artisan('pangi:sinkron-mappalus')->assertSuccessful();
        }

        Http::assertSentCount(SinkronMappalus::MAKS_PERCOBAAN_OTOMATIS);

        $this->sinkronManual();

        Http::assertSentCount(SinkronMappalus::MAKS_PERCOBAAN_OTOMATIS + 1);
        $this->assertSame(SinkronMappalus::MAKS_PERCOBAAN_OTOMATIS + 1, PengirimanMappalus::firstWhere('referensi', $usulan->no_usulan)->percobaan);
    }

    public function test_sinkron_ditolak_tanpa_token_atau_saat_sinkron_lain_berjalan(): void
    {
        $this->pelaksanaSelesai();
        $this->nominatifBertandaTangan();
        Http::fake();

        $kunci = Cache::lock('sinkron-mappalus', 60);
        $kunci->get();
        $this->sinkronManual()->assertSessionHas('error', 'Sinkron lain sedang berjalan. Coba lagi beberapa saat lagi.');
        $kunci->release();

        Pengaturan::simpanRahasia(Pengaturan::MAPPALUS_TOKEN, '');
        $this->sinkronManual()->assertSessionHas('error', 'Isi dulu alamat API dan token dari pengembang Mappalus.');

        Http::assertNothingSent();
    }

    public function test_tombol_sinkron_mati_saat_antrean_kosong(): void
    {
        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus'))
            ->assertSee('Sinkron Sekarang (0)')
            ->assertSee('kosong: true', false);
    }

    public function test_halaman_membedakan_status_dan_dapat_disaring(): void
    {
        $usulan = $this->pelaksanaSelesai('Maya Sari');
        $this->pelaksanaSelesai('Rina Kartika');
        $this->nominatifBertandaTangan();

        Http::fake([self::ALAMAT => Http::sequence()
            ->push(['diterima' => true], 200)
            ->push(['pesan' => 'ditolak'], 422)]);

        app(SinkronMappalus::class)->sinkron();

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus'))
            ->assertSee('data-baris-mappalus="berhasil"', false)
            ->assertSee('data-baris-mappalus="gagal"', false)
            ->assertSee('Kirim Ulang')
            ->assertSee('Sinkron Sekarang (1)')
            ->assertSee('kosong: false', false);

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus', ['status' => SinkronMappalus::GAGAL]))
            ->assertDontSee('data-baris-mappalus="berhasil"', false)
            ->assertSee('data-baris-mappalus="gagal"', false);

        $this->actingAs($this->admin)
            ->get(route('administrasi.mappalus', ['cari' => $usulan->no_usulan]))
            ->assertSee('Maya Sari - '.self::NO_TUGAS)
            ->assertDontSee('Rina Kartika - '.self::NO_TUGAS);
    }
}
