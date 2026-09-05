<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\TahunAnggaran;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * API baca-saja agar aplikasi lain dapat menampilkan angka dashboard
 * eksekutif tanpa membuka basis data PANGI.
 */
class ApiDashboardEksekutifTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-uji-coba-yang-cukup-panjang-untuk-dipakai';

    protected function setUp(): void
    {
        parent::setUp();

        config(['api.token' => self::TOKEN]);
    }

    /**
     * @param  array<string, string>  $header
     */
    private function panggil(string $jalur, array $header = []): TestResponse
    {
        return $this->getJson($jalur, $header + ['Authorization' => 'Bearer '.self::TOKEN]);
    }

    /**
     * Satu perjalanan yang sudah berlaku beserta biayanya, supaya angka yang
     * dibaca API benar-benar berasal dari data dan bukan nol semua.
     */
    private function perjalanan(int $tahun, float $biaya = 5_000_000): Usulan
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Keperawatan']);
        $pegawai = User::factory()->create(['id_unit' => $unit->id]);

        $usulan = Usulan::factory()->create([
            'id_user' => $pegawai->id,
            'status' => StatusUsulan::Disetujui->value,
            'id_kategori_perjadin' => KategoriPerjadin::factory()->create(['nama' => 'Luar Kota FullBoard'])->id,
            'tanggal_mulai' => $tahun.'-03-10',
            'tanggal_selesai' => $tahun.'-03-12',
        ]);

        $usulan->peserta()->create([
            'id_user' => $pegawai->id,
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'peran' => 'ketua',
        ]);

        Keuangan::factory()->lunas()->create(['id_usulan' => $usulan->id, 'total' => $biaya]);

        return $usulan;
    }

    // ── Penjagaan token ──

    public function test_permintaan_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/dashboard-eksekutif')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token API tidak dikenali.');
    }

    public function test_token_keliru_ditolak(): void
    {
        $this->getJson('/api/v1/dashboard-eksekutif', ['Authorization' => 'Bearer token-palsu'])
            ->assertUnauthorized();
    }

    /**
     * Gagal tertutup. Kalau API terbuka saat tokennya belum dipasang, satu
     * baris .env yang terlewat sudah cukup membocorkan data anggaran.
     */
    public function test_api_tertutup_selama_token_belum_dipasang(): void
    {
        config(['api.token' => null]);

        $this->getJson('/api/v1/dashboard-eksekutif')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Token API belum dipasang pada server. Hubungi administrator PANGI.');
    }

    public function test_header_x_api_token_juga_diterima(): void
    {
        $this->getJson('/api/v1/dashboard-eksekutif', ['X-Api-Token' => self::TOKEN])
            ->assertOk();
    }

    public function test_seluruh_jalur_dijaga_token(): void
    {
        $jalur = [
            '/api/v1/dashboard-eksekutif',
            '/api/v1/dashboard-eksekutif/ringkasan',
            '/api/v1/dashboard-eksekutif/realisasi',
            '/api/v1/dashboard-eksekutif/pegawai',
            '/api/v1/dashboard-eksekutif/tahun-anggaran',
        ];

        foreach ($jalur as $satu) {
            $this->getJson($satu)->assertUnauthorized();
            $this->panggil($satu)->assertOk();
        }
    }

    /**
     * API tidak boleh menjadi pintu belakang: sesi pengguna PANGI tidak
     * memberi akses, dan tokennya tetap wajib.
     */
    public function test_sesi_pengguna_tidak_menggantikan_token(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->getJson('/api/v1/dashboard-eksekutif')
            ->assertUnauthorized();
    }

    // ── Isi balasan ──

    public function test_seluruh_bagian_dashboard_terbaca_dalam_satu_permintaan(): void
    {
        $this->perjalanan(2026);

        $this->panggil('/api/v1/dashboard-eksekutif?tahun=2026')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tahun',
                    'bulan',
                    'ringkasan' => [
                        'pagu', 'total_realisasi', 'persentase_realisasi', 'sisa_pagu',
                        'akan_berangkat', 'sedang_berjalan', 'belum_melapor',
                    ],
                    'realisasi' => [
                        'total',
                        'per_bulan',
                        'per_kategori' => [['kategori', 'per_bulan', 'total']],
                    ],
                    'pegawai' => [
                        'per_bulan',
                        'per_unit' => [['unit', 'orang', 'perjalanan', 'biaya', 'per_bulan']],
                    ],
                ],
            ]);
    }

    public function test_realisasi_terbaca_pada_bulan_yang_benar(): void
    {
        $this->perjalanan(2026, 7_500_000);

        $isi = $this->panggil('/api/v1/dashboard-eksekutif/realisasi?tahun=2026')
            ->assertOk()
            ->json('data.realisasi');

        // JSON menuliskan pecahan bulat tanpa koma, jadi 7500000.0 terbaca
        // kembali sebagai bilangan bulat. Disamakan tipenya lebih dulu.
        $this->assertSame(7500000.0, (float) $isi['total']);
        // Berangkat Maret, jadi nilainya jatuh pada indeks ke-2.
        $this->assertSame(7500000.0, (float) $isi['per_bulan'][2]);
        $this->assertSame(0.0, (float) $isi['per_bulan'][0]);
        $this->assertSame('Luar Kota FullBoard', $isi['per_kategori'][0]['kategori']);
    }

    public function test_pegawai_terbaca_per_unit_kerja(): void
    {
        $this->perjalanan(2026);

        $unit = $this->panggil('/api/v1/dashboard-eksekutif/pegawai?tahun=2026')
            ->assertOk()
            ->json('data.pegawai.per_unit.0');

        $this->assertSame('Jurusan Keperawatan', $unit['unit']);
        $this->assertSame(1, $unit['orang']);
        $this->assertSame(1, $unit['perjalanan']);
    }

    public function test_persentase_dihitung_terhadap_pagu(): void
    {
        TahunAnggaran::factory()->create(['tahun' => 2026, 'pagu' => 100_000_000]);
        $this->perjalanan(2026, 25_000_000);

        $ringkasan = $this->panggil('/api/v1/dashboard-eksekutif/ringkasan?tahun=2026')
            ->assertOk()
            ->json('data.ringkasan');

        $this->assertSame(25.0, (float) $ringkasan['persentase_realisasi']);
        $this->assertSame(75000000.0, (float) $ringkasan['sisa_pagu']);
    }

    /**
     * Tahun anggaran yang pagunya belum diisi tidak boleh membuat API galat
     * karena pembagian dengan nol.
     */
    public function test_pagu_kosong_tidak_menggagalkan_permintaan(): void
    {
        $this->perjalanan(2026, 25_000_000);

        $ringkasan = $this->panggil('/api/v1/dashboard-eksekutif/ringkasan?tahun=2026')
            ->assertOk()
            ->json('data.ringkasan');

        $this->assertNull($ringkasan['persentase_realisasi']);
        $this->assertNull($ringkasan['sisa_pagu']);
    }

    public function test_tahun_anggaran_yang_tersedia_dapat_dibaca(): void
    {
        TahunAnggaran::factory()->create(['tahun' => 2025, 'is_aktif' => false]);
        TahunAnggaran::factory()->create(['tahun' => 2026, 'is_aktif' => true]);

        $this->panggil('/api/v1/dashboard-eksekutif/tahun-anggaran')
            ->assertOk()
            ->assertJsonPath('data.aktif', 2026)
            ->assertJsonPath('data.tersedia', [2026, 2025]);
    }

    public function test_tahun_diabaikan_bila_di_luar_rentang_wajar(): void
    {
        TahunAnggaran::factory()->create(['tahun' => 2026, 'is_aktif' => true]);

        $this->panggil('/api/v1/dashboard-eksekutif?tahun=99999')
            ->assertOk()
            ->assertJsonPath('data.tahun', 2026);
    }

    public function test_data_tahun_lain_tidak_ikut_terbaca(): void
    {
        $this->perjalanan(2025, 9_000_000);

        $total = $this->panggil('/api/v1/dashboard-eksekutif/realisasi?tahun=2026')
            ->assertOk()
            ->json('data.realisasi.total');

        $this->assertSame(0.0, (float) $total);
    }

    /**
     * Draf dan usulan yang batal bukan realisasi. Kalau ikut terhitung,
     * aplikasi pemanggil akan melaporkan serapan anggaran yang tidak pernah
     * benar-benar terjadi.
     */
    public function test_usulan_yang_belum_berlaku_tidak_dihitung(): void
    {
        $usulan = $this->perjalanan(2026, 4_000_000);
        $usulan->update(['status' => StatusUsulan::Draft->value]);

        $total = $this->panggil('/api/v1/dashboard-eksekutif/realisasi?tahun=2026')
            ->assertOk()
            ->json('data.realisasi.total');

        $this->assertSame(0.0, (float) $total);
    }

    // ── Baca saja ──

    public function test_api_tidak_menyediakan_jalur_yang_mengubah_data(): void
    {
        $metode = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($rute) => str_starts_with($rute->uri(), 'api/'))
            ->flatMap(fn ($rute) => $rute->methods())
            ->unique()
            ->values();

        $this->assertSame(['GET', 'HEAD'], $metode->all());
    }
}
