<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif;
use App\Models\KategoriPembiayaan;
use App\Models\PaguAkun;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Models\Usulan;
use App\Services\RingkasanEksekutif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pagu — angka maksimal — tiap kode akun dalam satu tahun anggaran, dan
 * realisasinya yang dibebankan menurut akun pada daftar nominatif.
 */
class PaguAkunTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TahunAnggaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->tahun = TahunAnggaran::factory()->aktif()->create(['tahun' => 2026, 'pagu' => 2_500_000_000]);
    }

    private function akun(string $kode): AkunPembiayaan
    {
        return AkunPembiayaan::firstOrCreate(['kode' => $kode], ['nama' => 'Belanja Perjalanan']);
    }

    private function sumber(string $kode): KategoriPembiayaan
    {
        return KategoriPembiayaan::where('kode', $kode)->firstOrFail();
    }

    private function simpanPagu(string $kode, ?string $sumber, int $pagu): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.tahun-anggaran.pagu-akun.simpan', $this->tahun), [
                'id_akun_pembiayaan' => $this->akun($kode)->id,
                'id_kategori_pembiayaan' => $sumber ? $this->sumber($sumber)->id : null,
                'pagu' => $pagu,
            ])
            ->assertSessionHasNoErrors();
    }

    /** Perjalanan dinas yang sudah disetujui, dibebankan ke akun lewat daftar nominatif surat tugasnya. */
    private function perjalanan(float $total, ?string $kodeAkun, string $status = StatusUsulan::Disetujui->value): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => $status,
            'tanggal_mulai' => '2026-05-10',
            'tanggal_selesai' => '2026-05-12',
        ]);
        $usulan->keuangan()->create(['total' => $total, 'uang_muka' => 0, 'sisa' => 0]);

        if ($kodeAkun) {
            DaftarNominatif::create(['no_tugas' => $usulan->no_tugas, 'id_akun_pembiayaan' => $this->akun($kodeAkun)->id]);
        }

        return $usulan;
    }

    public function test_pagu_tahun_anggaran_menjadi_jumlah_pagu_akunnya(): void
    {
        $this->simpanPagu('524111', 'RM', 437_132_000);
        $this->simpanPagu('525115', 'BLU', 1_794_295_000);
        $this->assertSame(2_231_427_000.0, $this->tahun->fresh()->pagu);

        // Akun yang sudah berpagu diperbarui, bukan digandakan.
        $this->simpanPagu('524111', 'RM', 500_000_000);
        $this->assertSame(2, $this->tahun->paguAkun()->count());
        $this->assertSame(2_294_295_000.0, $this->tahun->fresh()->pagu);

        $this->actingAs($this->admin)
            ->delete(route('master.tahun-anggaran.pagu-akun.hapus', [$this->tahun, $this->tahun->paguAkun()->first()]))
            ->assertSessionHas('success');
        $this->assertSame(1_794_295_000.0, $this->tahun->fresh()->pagu);
    }

    public function test_pagu_yang_sudah_dirinci_tidak_diubah_dari_formulir_tahun_anggaran(): void
    {
        $this->simpanPagu('524111', 'RM', 437_132_000);

        $this->actingAs($this->admin)
            ->put(route('master.tahun-anggaran.update', $this->tahun), ['tahun' => 2026, 'pagu' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame(437_132_000.0, $this->tahun->fresh()->pagu);
    }

    public function test_pagu_per_akun_menolak_akun_yang_tidak_ada_dan_pagu_negatif(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.tahun-anggaran.pagu-akun.simpan', $this->tahun), ['id_akun_pembiayaan' => 999_999, 'pagu' => -5])
            ->assertSessionHasErrors(['id_akun_pembiayaan', 'pagu']);

        $this->assertSame(0, PaguAkun::count());
    }

    public function test_pagu_per_akun_tertutup_bagi_pegawai_biasa(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('master.tahun-anggaran.pagu-akun', $this->tahun))
            ->assertForbidden();
    }

    /** Realisasi dibebankan ke akun pada daftar nominatif; yang belum berakun dikumpulkan tersendiri. */
    public function test_realisasi_dibebankan_ke_akun_daftar_nominatif(): void
    {
        $this->simpanPagu('524111', 'RM', 1_000_000);
        $this->simpanPagu('525115', 'BLU', 3_000_000);

        $this->perjalanan(400_000, '524111');
        $this->perjalanan(1_200_000, '525115');
        $this->perjalanan(100_000, null);
        // Draf bukan realisasi.
        $this->perjalanan(9_000_000, '524111', StatusUsulan::Draft->value);

        $anggaran = app(RingkasanEksekutif::class)->anggaranPerAkun(2026);
        $akun = $anggaran['akun']->keyBy('kode');

        $this->assertSame(400_000.0, $akun['524111']['realisasi']);
        $this->assertSame(600_000.0, $akun['524111']['sisa']);
        $this->assertSame(40.0, $akun['524111']['persen']);
        $this->assertSame('RM', $akun['524111']['sumber']);
        $this->assertSame(100_000.0, $anggaran['belum_berakun']);
        $this->assertSame(4_000_000.0, $anggaran['pagu']);

        $blu = $anggaran['sumber']->firstWhere('kode', 'BLU');
        $this->assertSame(1_200_000.0, $blu['realisasi']);
        $this->assertSame(75.0, $blu['porsi']);

        // Jumlah realisasi per akun sama dengan total realisasi dashboard.
        $this->assertSame(
            app(RingkasanEksekutif::class)->totalRealisasi(2026),
            $anggaran['akun']->sum('realisasi') + $anggaran['belum_berakun'],
        );
    }

    public function test_dashboard_dan_master_menampilkan_pagu_per_akun_dan_akun_yang_melebihi_pagu(): void
    {
        $this->simpanPagu('524111', 'RM', 1_000_000);
        $this->perjalanan(1_500_000, '524111');

        foreach ([route('dashboard-eksekutif', ['tahun' => 2026]), route('master.tahun-anggaran'), route('master.tahun-anggaran.pagu-akun', $this->tahun)] as $halaman) {
            $this->actingAs($this->admin)
                ->get($halaman)
                ->assertOk()
                ->assertSee('Pagu dan Realisasi')
                ->assertSeeInOrder(['524111', 'RM', 'Rp 1.000.000', 'Rp 1.500.000', 'Rp -500.000', 'Melebihi pagu'])
                ->assertSee('Rekap per Sumber Dana');
        }
    }

    /** Migrasi data mengisi pagu TA 2026 sesuai Rincian Kertas Kerja Satker. */
    public function test_migrasi_mengisi_pagu_akun_tahun_anggaran_2026(): void
    {
        $migrasi = require database_path('migrations/2026_10_14_015031_isi_pagu_akun_tahun_anggaran_2026.php');
        $migrasi->up();

        $this->assertSame(2_375_475_000.0, $this->tahun->fresh()->pagu);

        $pagu = $this->tahun->paguAkun()->with('akun', 'sumberDana')->get()->keyBy(fn (PaguAkun $baris) => $baris->akun->kode);
        $this->assertSame(437_132_000.0, $pagu['524111']->pagu);
        $this->assertSame(144_048_000.0, $pagu['524119']->pagu);
        $this->assertSame(1_794_295_000.0, $pagu['525115']->pagu);
        $this->assertSame('BLU', $pagu['525115']->sumberDana->kode);
        $this->assertSame('Belanja Perjalanan', $pagu['525115']->akun->nama);
        $this->assertSame('RM', $pagu['524119']->sumberDana->kode);

        // Dijalankan ulang tidak menggandakan apa pun.
        $migrasi->up();
        $this->assertSame(3, $this->tahun->paguAkun()->count());
        $this->assertSame(1, AkunPembiayaan::where('kode', '525115')->count());
    }
}
