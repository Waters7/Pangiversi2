<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Rekap keberangkatan per unit kerja pada dashboard eksekutif.
 */
class RekapUnitKerjaTest extends TestCase
{
    use RefreshDatabase;

    private int $tahun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahun = now()->year;
    }

    private function pimpinan(): User
    {
        return User::factory()->create(['role' => PeranPengguna::Pimpinan->value]);
    }

    /**
     * Satu perjalanan yang sudah disetujui, lengkap dengan pesertanya.
     */
    private function perjalanan(UnitKerja $unit, string $tanggalMulai, float $biaya = 0, int $jumlahPeserta = 1): Usulan
    {
        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalMulai,
        ]);

        for ($i = 0; $i < $jumlahPeserta; $i++) {
            PesertaUsulan::factory()->create([
                'id_usulan' => $usulan->id,
                'id_user' => User::factory()->create(['id_unit' => $unit->id])->id,
            ]);
        }

        if ($biaya > 0) {
            Keuangan::factory()->create(['id_usulan' => $usulan->id, 'total' => $biaya]);
        }

        return $usulan;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function rekap(): Collection
    {
        $response = $this->actingAs($this->pimpinan())
            ->get(route('dashboard-eksekutif'))
            ->assertOk();

        return collect($response->viewData('pegawaiPerUnit'));
    }

    private function barisUnit(string $nama): ?array
    {
        return $this->rekap()->firstWhere('unit', $nama);
    }

    // ── Pengelompokan ──

    public function test_rekap_dikelompokkan_per_unit_kerja(): void
    {
        $jurusan = UnitKerja::factory()->create(['nama' => 'Jurusan Keperawatan']);
        $direktorat = UnitKerja::factory()->create(['nama' => 'Direktorat']);

        $this->perjalanan($jurusan, "{$this->tahun}-03-10");
        $this->perjalanan($direktorat, "{$this->tahun}-05-04");

        $rekap = $this->rekap();

        $this->assertSame(1, $this->barisUnit('Jurusan Keperawatan')['orang']);
        $this->assertSame(1, $this->barisUnit('Direktorat')['orang']);
        $this->assertCount(2, $rekap);
    }

    public function test_jumlah_orang_per_bulan_diletakkan_pada_kolom_bulannya(): void
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Gizi']);

        $this->perjalanan($unit, "{$this->tahun}-03-10", jumlahPeserta: 2);
        $this->perjalanan($unit, "{$this->tahun}-11-02");

        $baris = $this->barisUnit('Jurusan Gizi');

        $this->assertSame(2, $baris['perBulan'][2]);   // Maret
        $this->assertSame(1, $baris['perBulan'][10]);  // November
        $this->assertSame(0, $baris['perBulan'][0]);
        $this->assertSame(3, $baris['orang']);
        $this->assertSame(2, $baris['perjalanan']);
        $this->assertCount(12, $baris['perBulan']);
    }

    public function test_biaya_per_unit_dijumlahkan(): void
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Farmasi']);

        $this->perjalanan($unit, "{$this->tahun}-02-01", biaya: 3_000_000);
        $this->perjalanan($unit, "{$this->tahun}-04-01", biaya: 1_500_000);

        $this->assertSame(4_500_000.0, $this->barisUnit('Jurusan Farmasi')['biaya']);
    }

    public function test_pegawai_tanpa_unit_dikumpulkan_terpisah(): void
    {
        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => "{$this->tahun}-06-01",
            'tanggal_selesai' => "{$this->tahun}-06-02",
        ]);

        PesertaUsulan::factory()->create(['id_usulan' => $usulan->id, 'id_user' => null]);

        $this->assertSame(1, $this->barisUnit('Tanpa Unit')['orang']);
    }

    // ── Penyaringan ──

    public function test_usulan_yang_belum_disetujui_tidak_dihitung(): void
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Kebidanan']);

        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::MenungguPpk->value,
            'tanggal_mulai' => "{$this->tahun}-07-01",
            'tanggal_selesai' => "{$this->tahun}-07-02",
        ]);
        PesertaUsulan::factory()->create([
            'id_usulan' => $usulan->id,
            'id_user' => User::factory()->create(['id_unit' => $unit->id])->id,
        ]);

        $this->assertNull($this->barisUnit('Jurusan Kebidanan'));
    }

    public function test_perjalanan_tahun_lain_tidak_ikut_terhitung(): void
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Analis']);

        $this->perjalanan($unit, ($this->tahun - 1).'-08-01');

        $this->assertNull($this->barisUnit('Jurusan Analis'));
    }

    public function test_unit_dengan_pemberangkatan_terbanyak_tampil_lebih_dulu(): void
    {
        $sedikit = UnitKerja::factory()->create(['nama' => 'Unit Sedikit']);
        $banyak = UnitKerja::factory()->create(['nama' => 'Unit Banyak']);

        $this->perjalanan($sedikit, "{$this->tahun}-01-05");
        $this->perjalanan($banyak, "{$this->tahun}-01-05", jumlahPeserta: 3);

        $this->assertSame('Unit Banyak', $this->rekap()->first()['unit']);
    }

    // ── Tampilan ──

    public function test_tabel_rekap_unit_tampil_pada_dashboard(): void
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Kesehatan Lingkungan']);
        $this->perjalanan($unit, "{$this->tahun}-09-12", biaya: 2_000_000);

        $this->actingAs($this->pimpinan())
            ->get(route('dashboard-eksekutif'))
            ->assertOk()
            ->assertSee('Jurusan Kesehatan Lingkungan')
            ->assertSee('Rekap per Unit Kerja');
    }

    public function test_dashboard_tetap_terbuka_saat_belum_ada_data(): void
    {
        $this->assertTrue($this->rekap()->isEmpty());
    }
}
