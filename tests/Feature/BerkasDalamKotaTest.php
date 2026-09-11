<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\RuasTransport;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use App\Services\SinkronBiayaDokumen;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    /** Hanya SPPD dan satu nota transport lokal — kelengkapan minimum dalam kota. */
    private function isiBerkasDalamKota(Usulan $usulan): void
    {
        Dokumen::updateOrCreate(['id_usulan' => $usulan->id], ['sppd' => 'demo/sppd.pdf']);

        $usulan->notaTransport()->updateOrCreate(['urutan' => RuasTransport::Lokal->value], [
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

    /**
     * Dalam kota tidak melewati bandara: seksi nota hanya satu baris
     * transport lokal — nominal, keterangan, dan bukti — bukan empat ruas.
     */
    public function test_formulir_dalam_kota_hanya_satu_baris_transport_lokal(): void
    {
        $usulan = $this->usulan(dalamKota: true);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $usulan->no_usulan))
            ->assertOk()
            ->assertSee('Transport lokal')
            ->assertSee('name="ruas['.RuasTransport::Lokal->value.'][nominal]"', escape: false)
            ->assertSee('name="ruas['.RuasTransport::Lokal->value.'][keterangan]"', escape: false)
            ->assertSee('name="ruas['.RuasTransport::Lokal->value.'][bukti]"', escape: false)
            ->assertDontSee('Rumah ke bandara')
            ->assertDontSee('Bandara ke rumah pelaksana')
            ->assertDontSee('Empat ruas');
    }

    public function test_formulir_luar_kota_tetap_lengkap(): void
    {
        $usulan = $this->usulan(dalamKota: false);

        $this->actingAs($this->pelaksana)
            ->get(route('dokumen.show', $usulan->no_usulan))
            ->assertOk()
            ->assertDontSee('Perjalanan dinas dalam kota')
            ->assertSee('2a. Tiket Pergi')
            ->assertSee('4. Akomodasi')
            ->assertSee('Rumah ke bandara')
            ->assertSee('Bandara ke rumah pelaksana');
    }

    // ── Menyimpan nota transport lokal ──

    public function test_nota_dalam_kota_tersimpan_sebagai_satu_ruas_transport_lokal(): void
    {
        $usulan = $this->usulan(dalamKota: true);

        $this->actingAs($this->pelaksana)
            ->post(route('dokumen.store', $usulan), [
                'section' => 'nota',
                'ruas' => [
                    RuasTransport::Lokal->value => [
                        'nominal' => 80_000,
                        'keterangan' => 'Ojek daring PP ke lokasi kegiatan',
                        'bukti' => UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $nota = $usulan->notaTransport()->get();

        $this->assertCount(1, $nota);
        $this->assertSame(RuasTransport::Lokal->value, $nota->first()->urutan);
        $this->assertSame(80_000.0, $nota->first()->nominal);
        $this->assertSame('Ojek daring PP ke lokasi kegiatan', $nota->first()->keterangan);
        $this->assertNotNull($nota->first()->bukti);
    }

    public function test_nota_dalam_kota_bernominal_wajib_berbukti(): void
    {
        $usulan = $this->usulan(dalamKota: true);

        $this->actingAs($this->pelaksana)
            ->post(route('dokumen.store', $usulan), [
                'section' => 'nota',
                'ruas' => [RuasTransport::Lokal->value => ['nominal' => 80_000]],
            ])
            ->assertSessionHasErrors('ruas.'.RuasTransport::Lokal->value.'.bukti');
    }

    /**
     * Ruas bandara yang tersisa — misalnya kategori usulan berpindah ke
     * dalam kota — dibuang saat nota disimpan, supaya tidak ikut ke daftar
     * riil.
     */
    public function test_ruas_bandara_yang_tersisa_dibuang_saat_nota_dalam_kota_disimpan(): void
    {
        $usulan = $this->usulan(dalamKota: true);

        $usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 50_000, 'bukti' => 'demo/lama.pdf']);

        $this->actingAs($this->pelaksana)
            ->post(route('dokumen.store', $usulan), [
                'section' => 'nota',
                'ruas' => [
                    RuasTransport::Lokal->value => [
                        'nominal' => 80_000,
                        'bukti' => UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf'),
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [RuasTransport::Lokal->value],
            $usulan->notaTransport()->pluck('urutan')->all(),
        );
    }

    public function test_checklist_dan_daftar_riil_menyebut_transport_lokal(): void
    {
        $usulan = $this->usulan(dalamKota: true);
        $this->isiBerkasDalamKota($usulan);

        $usulan->peserta()->create(['id_user' => $this->pelaksana->id, 'nama' => $this->pelaksana->nama, 'peran' => 'ketua']);
        app(SinkronBiayaDokumen::class)->selaraskanDaftarRiil($usulan->fresh());

        $baris = collect($this->penagih()->checklist($usulan->fresh()))
            ->firstWhere('label', 'Nota Transportasi Lokal');

        $this->assertTrue($baris['terpenuhi']);
        $this->assertSame(['Transport lokal'], array_column($baris['berkas'], 'label'));

        $daftar = DaftarRiil::firstWhere('id_usulan', $usulan->id);

        $this->assertSame(
            ['Transport lokal — Kantor ke lokasi kegiatan'],
            $daftar->rincian()->pluck('uraian')->all(),
        );
        $this->assertSame(75_000.0, (float) $daftar->fresh()->total_riil);
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
