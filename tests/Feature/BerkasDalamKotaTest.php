<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kelengkapan berkas bercabang menurut wilayah perjalanannya.
 *
 * Perjalanan luar kota melibatkan tiket, penginapan, dan kuitansi.
 * Perjalanan dalam kota tidak satu pun dari itu — yang ada hanya surat
 * tugas (terkunci dari SPD), SPPD, dan nota transportasi lokalnya. Menagih
 * bill hotel kepada orang yang pulang hari itu juga hanya memacetkan berkas.
 */
class BerkasDalamKotaTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Kategori perjadin adalah master data; penanda wilayahnya ikut
        // tersimpan di sana, jadi seedernya dijalankan lebih dulu.
        $this->seed(KategoriPerjadinSeeder::class);

        $this->pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);
    }

    private function usulan(bool $dalamKota): Usulan
    {
        $kategori = KategoriPerjadin::where('dalam_kota', $dalamKota)->firstOrFail();

        return Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'id_kategori_perjadin' => $kategori->id,
        ]);
    }

    /** Hanya SPPD dan satu ruas nota — kelengkapan minimum dalam kota. */
    private function isiBerkasDalamKota(Usulan $usulan): void
    {
        Dokumen::updateOrCreate(['id_usulan' => $usulan->id], ['sppd' => 'demo/sppd.pdf']);

        $usulan->notaTransport()->updateOrCreate(['urutan' => 1], [
            'nominal' => 75_000,
            'keterangan' => 'Kantor ke lokasi kegiatan',
            'bukti' => 'demo/nota.pdf',
        ]);

        $usulan->laporan()->create(['diselesaikan_at' => now()]);
    }

    private function penagih(): PenagihDokumen
    {
        return app(PenagihDokumen::class);
    }

    // ── Master data ──

    public function test_kategori_mengenali_wilayah_perjalanannya(): void
    {
        $dalam = KategoriPerjadin::firstWhere('nama', 'Dalam Kota FullDay');
        $luar = KategoriPerjadin::firstWhere('nama', 'Luar Kota FullBoard');

        $this->assertTrue($dalam->dalamKota());
        $this->assertSame('Dalam Kota', $dalam->labelWilayah());

        $this->assertFalse($luar->dalamKota());
        $this->assertSame('Luar Kota', $luar->labelWilayah());
    }

    /**
     * Narasumber dan Diklat memuat keduanya dalam satu grup, jadi penandanya
     * harus mengikuti nama kategorinya — bukan grupnya.
     */
    public function test_kategori_narasumber_dan_diklat_ikut_terpilah(): void
    {
        $this->assertTrue(KategoriPerjadin::firstWhere('nama', 'Narasumber Dalam Kota Fullday')->dalamKota());
        $this->assertTrue(KategoriPerjadin::firstWhere('nama', 'Diklat Dalam Kota')->dalamKota());

        $this->assertFalse(KategoriPerjadin::firstWhere('nama', 'Narasumber Luar Kota FullBoard')->dalamKota());
        $this->assertFalse(KategoriPerjadin::firstWhere('nama', 'Diklat Luar Kota FullBoard')->dalamKota());
    }

    public function test_usulan_tanpa_kategori_dianggap_luar_kota(): void
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'id_kategori_perjadin' => null,
        ]);

        // Lebih aman menagih berkas yang ternyata tidak perlu daripada
        // melewatkan yang wajib.
        $this->assertFalse($usulan->dalamKota());
    }

    // ── Kelengkapan berkas ──

    public function test_dalam_kota_lengkap_dengan_sppd_dan_nota_saja(): void
    {
        $usulan = $this->usulan(dalamKota: true);
        $this->isiBerkasDalamKota($usulan);

        $this->assertSame([], $this->penagih()->berkasKurang($usulan->fresh()));
    }

    public function test_dalam_kota_tidak_ditagih_tiket_hotel_maupun_kuitansi(): void
    {
        $usulan = $this->usulan(dalamKota: true);
        $kurang = $this->penagih()->berkasKurang($usulan);

        foreach (['Tiket Pergi', 'Tiket Pulang', 'Bill hotel', 'Kuitansi'] as $seharusnyaTidakAda) {
            $this->assertNotContains($seharusnyaTidakAda, $kurang);
        }
    }

    public function test_dalam_kota_tetap_menagih_sppd_dan_nota(): void
    {
        $usulan = $this->usulan(dalamKota: true);
        $kurang = $this->penagih()->berkasKurang($usulan);

        $this->assertContains('SPPD bertanda tangan', $kurang);
        $this->assertContains('Nota/biaya transportasi lokal', $kurang);
    }

    public function test_luar_kota_dengan_berkas_yang_sama_masih_kurang(): void
    {
        $usulan = $this->usulan(dalamKota: false);
        $this->isiBerkasDalamKota($usulan);

        $kurang = $this->penagih()->berkasKurang($usulan->fresh());

        $this->assertNotEmpty($kurang, 'Luar kota seharusnya masih menagih tiket dan penginapan.');
        $this->assertContains('Bill hotel', $kurang);
        $this->assertContains('Kuitansi', $kurang);
    }

    // ── Checklist ──

    public function test_checklist_dalam_kota_hanya_memuat_yang_berlaku(): void
    {
        $usulan = $this->usulan(dalamKota: true);

        $label = collect($this->penagih()->checklist($usulan))->pluck('label');

        $this->assertEqualsCanonicalizing(
            ['SPPD Bertanda Tangan', 'Nota Transportasi Lokal', 'Laporan Perjalanan Dinas'],
            $label->all()
        );
    }

    public function test_checklist_luar_kota_memuat_seluruhnya(): void
    {
        $usulan = $this->usulan(dalamKota: false);

        $label = collect($this->penagih()->checklist($usulan))->pluck('label');

        $this->assertContains('Tiket Pergi', $label);
        $this->assertContains('Bill Hotel', $label);
        $this->assertContains('Kuitansi', $label);
    }

    // ── Formulir dokumen ──

    public function test_formulir_dalam_kota_menyembunyikan_bagian_yang_tidak_berlaku(): void
    {
        $usulan = $this->usulan(dalamKota: true);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $usulan->no_usulan))
            ->assertOk()
            ->assertSee('Perjalanan dinas dalam kota')
            ->assertSee('3. Nota / Bukti Biaya Transportasi')
            ->assertDontSee('2a. Tiket Pergi')
            ->assertDontSee('4. Akomodasi');
    }

    public function test_formulir_luar_kota_tetap_lengkap(): void
    {
        $usulan = $this->usulan(dalamKota: false);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $usulan->no_usulan))
            ->assertOk()
            ->assertDontSee('Perjalanan dinas dalam kota')
            ->assertSee('2a. Tiket Pergi')
            ->assertSee('4. Akomodasi');
    }

    // ── Master data dapat menyesuaikan ──

    public function test_administrator_dapat_mengubah_penanda_wilayah(): void
    {
        $admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $kategori = KategoriPerjadin::firstWhere('nama', 'Luar Kota FullBoard');

        $this->actingAs($admin)
            ->put(route('master.kategori-perjadin.update', $kategori), [
                'grup' => $kategori->grup,
                'nama' => $kategori->nama,
                'kode' => $kategori->kode,
                'dalam_kota' => '1',
                'is_aktif' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($kategori->fresh()->dalamKota());
    }
}
