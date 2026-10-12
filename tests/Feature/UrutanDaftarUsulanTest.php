<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Daftar usulan dapat diurutkan, disaring menurut jalur dan unit kerja, dan
 * diatur banyak barisnya — supaya usulan yang dicari cepat ditemukan.
 */
class UrutanDaftarUsulanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function usulan(array $isi = []): Usulan
    {
        return Usulan::factory()->create($isi + [
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);
    }

    /**
     * Nomor usulan pada daftar, sesuai urutan tampilnya.
     *
     * @return list<string>
     */
    private function nomorTampil(TestResponse $respons): array
    {
        return $respons->assertOk()->viewData('usulan')->getCollection()->pluck('no_usulan')->all();
    }

    public function test_daftar_diurutkan_menurut_pilihan_pengguna(): void
    {
        $manado = $this->usulan(['no_usulan' => 'PJ-B', 'lokasi' => 'Manado', 'tanggal_mulai' => '2026-03-10']);
        $bitung = $this->usulan(['no_usulan' => 'PJ-C', 'lokasi' => 'Bitung', 'tanggal_mulai' => '2026-01-05']);
        $tomohon = $this->usulan(['no_usulan' => 'PJ-A', 'lokasi' => 'Tomohon', 'tanggal_mulai' => '2026-06-20']);

        $urutan = fn (?string $urut) => $this->nomorTampil(
            $this->actingAs($this->pelaksana)->get(route('usulan.saya', array_filter(['urut' => $urut])))
        );

        $this->assertSame([$tomohon->no_usulan, $manado->no_usulan, $bitung->no_usulan], $urutan(null));
        $this->assertSame([$bitung->no_usulan, $manado->no_usulan, $tomohon->no_usulan], $urutan('berangkat-terlama'));
        $this->assertSame([$bitung->no_usulan, $manado->no_usulan, $tomohon->no_usulan], $urutan('tujuan'));
        $this->assertSame(['PJ-A', 'PJ-B', 'PJ-C'], $urutan('nomor'));

        // Urutan yang tidak dikenal kembali ke tanggal berangkat terbaru.
        $this->assertSame($urutan(null), $urutan('acak'));
    }

    public function test_urutan_pelaksana_hanya_pada_daftar_seluruh_usulan(): void
    {
        $zaenal = $this->usulan(['id_user' => User::factory()->create(['nama' => 'Zaenal Arifin'])->id, 'tanggal_mulai' => '2026-06-01']);
        $agus = $this->usulan(['id_user' => User::factory()->create(['nama' => 'Agus Salim'])->id, 'tanggal_mulai' => '2026-02-01']);

        $this->assertSame(
            [$agus->no_usulan, $zaenal->no_usulan],
            $this->nomorTampil($this->actingAs($this->admin)->get(route('usulan.list', ['urut' => 'pelaksana']))),
        );

        // Usulan Perjadin Saya berisi satu pelaksana: pilihannya tidak ditawarkan.
        $this->actingAs($this->admin)
            ->get(route('usulan.saya', ['urut' => 'pelaksana']))
            ->assertOk()
            ->assertViewHas('urut', 'berangkat-terbaru')
            ->assertDontSee('Nama pelaksana (A–Z)');
    }

    /** Judul kolom mengurutkan daftar, dan klik kedua membalik arahnya tanpa membuang saringan. */
    public function test_judul_kolom_mengurutkan_dan_membalik_arah(): void
    {
        $this->usulan(['status' => StatusUsulan::Draft->value]);

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.saya', ['status' => StatusUsulan::Draft->value]))
            ->assertOk()
            ->assertSee(route('usulan.saya').'?status=draft&urut=berangkat-terlama');

        $this->actingAs($this->pelaksana)
            ->get(route('usulan.saya', ['status' => StatusUsulan::Draft->value, 'urut' => 'berangkat-terlama', 'page' => 2]))
            ->assertOk()
            ->assertSee(route('usulan.saya').'?status=draft&urut=berangkat-terbaru');
    }

    /** Usulan lama yang belum menyimpan jalurnya ikut tersaring menurut kategorinya. */
    public function test_saringan_jalur_menyertakan_usulan_lama_menurut_kategorinya(): void
    {
        $kategoriDalamKota = KategoriPerjadin::factory()->create(['grup' => 'Perjadin Dalam Kota', 'dalam_kota' => true]);
        $kategoriLuarKota = KategoriPerjadin::factory()->create(['grup' => 'Perjadin Luar Kota', 'dalam_kota' => false]);
        $kategoriSupervisi = KategoriPerjadin::factory()->create(['grup' => JenisPerjadin::GRUP_SUPERVISI, 'dalam_kota' => true]);

        $dalamKota = $this->usulan(['jenis_perjadin' => JenisPerjadin::DalamKota->value, 'id_kategori_perjadin' => $kategoriDalamKota->id]);
        $dalamKotaLama = $this->usulan(['jenis_perjadin' => null, 'id_kategori_perjadin' => $kategoriDalamKota->id]);
        $provinsi = $this->usulan(['jenis_perjadin' => JenisPerjadin::LuarKotaDalamProvinsi->value, 'id_kategori_perjadin' => $kategoriLuarKota->id]);
        $luarKotaLama = $this->usulan(['jenis_perjadin' => null, 'id_kategori_perjadin' => $kategoriLuarKota->id]);
        $tanpaKategori = $this->usulan(['jenis_perjadin' => null, 'id_kategori_perjadin' => null]);
        $supervisiLama = $this->usulan(['jenis_perjadin' => null, 'id_kategori_perjadin' => $kategoriSupervisi->id]);

        $tersaring = fn (JenisPerjadin $jalur) => collect($this->nomorTampil(
            $this->actingAs($this->pelaksana)->get(route('usulan.saya', ['jalur' => $jalur->value]))
        ))->sort()->values()->all();
        $nomor = fn (Usulan ...$usulan) => collect($usulan)->pluck('no_usulan')->sort()->values()->all();

        $this->assertSame($nomor($dalamKota, $dalamKotaLama), $tersaring(JenisPerjadin::DalamKota));
        $this->assertSame($nomor($provinsi), $tersaring(JenisPerjadin::LuarKotaDalamProvinsi));
        $this->assertSame($nomor($luarKotaLama, $tanpaKategori), $tersaring(JenisPerjadin::LuarKota));
        $this->assertSame($nomor($supervisiLama), $tersaring(JenisPerjadin::Supervisi));
    }

    /** Saringan unit dan pencarian nama pelaksana ikut menentukan angka pada tab status. */
    public function test_daftar_seluruh_usulan_disaring_menurut_unit_dan_nama_pelaksana(): void
    {
        $gizi = UnitKerja::factory()->create(['nama' => 'Jurusan Gizi']);
        $farmasi = UnitKerja::factory()->create(['nama' => 'Jurusan Farmasi']);

        $usulanGizi = $this->usulan(['id_user' => User::factory()->create(['nama' => 'Rina Pongoh', 'id_unit' => $gizi->id])->id]);
        $usulanFarmasi = $this->usulan(['id_user' => User::factory()->create(['nama' => 'Johan Lumi', 'id_unit' => $farmasi->id])->id]);

        $perUnit = $this->actingAs($this->admin)->get(route('usulan.list', ['unit' => $gizi->id]));
        $this->assertSame([$usulanGizi->no_usulan], $this->nomorTampil($perUnit));
        $perUnit->assertViewHas('totalUsulan', 1)->assertSee('Jurusan Farmasi');

        $this->assertSame(
            [$usulanFarmasi->no_usulan],
            $this->nomorTampil($this->actingAs($this->admin)->get(route('usulan.list', ['search' => 'Johan']))),
        );

        // Pelaksana biasa tidak diberi saringan unit kerja.
        $this->actingAs($this->pelaksana)
            ->get(route('usulan.saya'))
            ->assertOk()
            ->assertDontSee('Unit kerja pelaksana');
    }

    public function test_banyak_baris_per_halaman_dapat_dipilih(): void
    {
        foreach (range(1, 12) as $i) {
            $this->usulan();
        }

        $jumlah = fn (?int $per) => count($this->nomorTampil(
            $this->actingAs($this->pelaksana)->get(route('usulan.saya', array_filter(['per' => $per])))
        ));

        $this->assertSame(10, $jumlah(null));
        $this->assertSame(12, $jumlah(25));
        // Angka di luar pilihan kembali ke bawaan.
        $this->assertSame(10, $jumlah(1000));
    }
}
