<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\LokasiTujuan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\KoordinatKota;
use App\Services\PemetaPerjalanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Peta kota tujuan perjalanan dinas pada menu Jadwal Perjalanan, dipilah
 * menjadi dalam kota & sekitarnya (Sulawesi Utara) dan luar kota.
 */
class PetaPerjalananTest extends TestCase
{
    use RefreshDatabase;

    private User $timSdm;

    private KategoriPerjadin $dalamKota;

    private KategoriPerjadin $luarKota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timSdm = User::factory()->create(['role' => PeranPengguna::TimSdm->value]);
        $this->dalamKota = KategoriPerjadin::factory()->create(['dalam_kota' => true, 'nama' => 'Dalam Kota FullDay']);
        $this->luarKota = KategoriPerjadin::factory()->create(['dalam_kota' => false, 'nama' => 'Luar Kota FullBoard']);
    }

    private function perjalanan(string $lokasi, KategoriPerjadin $kategori, array $ubah = [], int $peserta = 1): Usulan
    {
        $usulan = Usulan::factory()->create(array_merge([
            'lokasi' => $lokasi,
            'id_lokasi' => LokasiTujuan::whereRaw('LOWER(nama) = ?', [mb_strtolower($lokasi)])->value('id'),
            'id_kategori_perjadin' => $kategori->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-07',
        ], $ubah));

        PesertaUsulan::factory()->count($peserta)->create(['id_usulan' => $usulan->id]);

        return $usulan;
    }

    // ── Koordinat bawaan ──

    public function test_kota_lazim_dikenal_tanpa_mengisi_koordinat(): void
    {
        $koordinat = new KoordinatKota;

        $this->assertSame([1.4748, 124.8421], $koordinat->cari('Manado'));
        $this->assertSame([1.4748, 124.8421], $koordinat->cari('Kota Manado, Sulawesi Utara'));
        $this->assertSame([-6.2088, 106.8456], $koordinat->cari('JAKARTA'));
        $this->assertNull($koordinat->cari('Kota Antah Berantah'));
        $this->assertNull($koordinat->cari(null));
    }

    // ── Pemilahan dalam / luar kota ──

    public function test_peta_memilah_kota_menurut_wilayah_tujuan(): void
    {
        $this->perjalanan('Manado', $this->dalamKota, peserta: 2);
        // Tomohon tetap "sekitarnya" walau kategorinya luar kota.
        $this->perjalanan('Tomohon', $this->luarKota);
        $this->perjalanan('Jakarta', $this->luarKota, peserta: 3);
        $this->perjalanan('Jakarta', $this->luarKota, ['tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-02']);
        $this->perjalanan('Makassar', $this->luarKota);

        $pemeta = app(PemetaPerjalanan::class);

        $dalam = $pemeta->susun(PemetaPerjalanan::DALAM_KOTA);
        $this->assertSame(['Manado', 'Tomohon'], array_column($dalam['kota'], 'nama'));
        $this->assertSame(['kota' => 2, 'perjalanan' => 2, 'pegawai' => 3], $dalam['ringkasan']);

        $luar = $pemeta->susun(PemetaPerjalanan::LUAR_KOTA);
        $this->assertSame(['Jakarta', 'Makassar'], array_column($luar['kota'], 'nama'));
        $jakarta = $luar['kota'][0];
        $this->assertSame(2, $jakarta['perjalanan']);
        $this->assertSame(4, $jakarta['pegawai']);
        $this->assertSame(-6.2088, $jakarta['lintang']);
        $this->assertSame('01 Nov 2026', $jakarta['terakhir']);
        $this->assertCount(2, $jakarta['daftar']);
        $this->assertSame('01 – 02 Nov 2026', $jakarta['daftar'][0]['tanggal']);
    }

    public function test_kota_yang_tidak_dikenal_mengikuti_kategori_perjadin(): void
    {
        LokasiTujuan::create(['nama' => 'Kema', 'provinsi' => 'Sulawesi Utara', 'jenis' => 'luar_kota', 'lintang' => 1.37, 'bujur' => 125.07]);

        $this->perjalanan('Kema', $this->luarKota);
        $this->perjalanan('Desa Antah', $this->dalamKota);
        $this->perjalanan('Kota Entah', $this->luarKota);

        $pemeta = app(PemetaPerjalanan::class);
        $dalam = $pemeta->susun(PemetaPerjalanan::DALAM_KOTA);
        $luar = $pemeta->susun(PemetaPerjalanan::LUAR_KOTA);

        // Provinsi pada master lokasi menjadikannya "sekitarnya"; kota tanpa
        // koordinat maupun master mengikuti kategori usulannya.
        $this->assertSame(['Kema'], array_column($dalam['kota'], 'nama'));
        $this->assertSame(['Desa Antah'], array_column($dalam['tanpaKoordinat'], 'nama'));
        $this->assertSame(['Kota Entah'], array_column($luar['tanpaKoordinat'], 'nama'));
    }

    public function test_draf_dan_yang_ditolak_tidak_dipetakan_dan_tahun_dapat_disaring(): void
    {
        $this->perjalanan('Jakarta', $this->luarKota, ['status' => StatusUsulan::Draft->value]);
        $this->perjalanan('Jakarta', $this->luarKota, ['status' => StatusUsulan::Ditolak->value]);
        $this->perjalanan('Bandung', $this->luarKota, ['status' => StatusUsulan::MenungguPpk->value]);
        $this->perjalanan('Surabaya', $this->luarKota, ['tanggal_mulai' => '2025-03-01', 'tanggal_selesai' => '2025-03-02']);

        $pemeta = app(PemetaPerjalanan::class);

        $this->assertSame(['Bandung', 'Surabaya'], array_column($pemeta->susun(PemetaPerjalanan::LUAR_KOTA)['kota'], 'nama'));
        $this->assertSame(['Bandung'], array_column($pemeta->susun(PemetaPerjalanan::LUAR_KOTA, 2026)['kota'], 'nama'));
        $this->assertSame([2026, 2025], $pemeta->tahunTersedia());
    }

    public function test_koordinat_master_lokasi_menimpa_daftar_bawaan_dan_kota_asing_dilaporkan(): void
    {
        LokasiTujuan::create(['nama' => 'Jakarta', 'provinsi' => 'DKI Jakarta', 'jenis' => 'luar_kota', 'lintang' => -6.1, 'bujur' => 106.8]);
        LokasiTujuan::create(['nama' => 'Kota Antah', 'provinsi' => 'Nusantara', 'jenis' => 'luar_kota']);

        $this->perjalanan('Jakarta', $this->luarKota);
        $this->perjalanan('Kota Antah', $this->luarKota);

        $peta = app(PemetaPerjalanan::class)->susun(PemetaPerjalanan::LUAR_KOTA);

        $this->assertSame([-6.1, 106.8], [$peta['kota'][0]['lintang'], $peta['kota'][0]['bujur']]);
        $this->assertSame([['nama' => 'Kota Antah', 'perjalanan' => 1]], $peta['tanpaKoordinat']);
        $this->assertSame(2, $peta['ringkasan']['kota']);
    }

    // ── Halaman ──

    public function test_halaman_peta_terbuka_bagi_yang_boleh_melihat_jadwal(): void
    {
        $this->perjalanan('Jakarta', $this->luarKota);
        $this->perjalanan('Manado', $this->dalamKota);

        $this->actingAs($this->timSdm)
            ->get(route('jadwal-perjalanan.peta', 'luar-kota'))
            ->assertOk()
            ->assertSee('Perjalanan Luar Kota')
            ->assertSee('Jakarta')
            ->assertDontSee('>Manado<', false)
            ->assertSee('leaflet', false)
            ->assertSee('id="petaPerjadin"', false);

        $this->actingAs($this->timSdm)
            ->get(route('jadwal-perjalanan.peta', 'dalam-kota'))
            ->assertOk()
            ->assertSee('Dalam Kota &amp; Sekitarnya', false)
            ->assertSee('Manado');

        $this->actingAs($this->timSdm)->get(route('jadwal-perjalanan.peta', 'luar-negeri'))->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::DosenTendik->value]))
            ->get(route('jadwal-perjalanan.peta', 'luar-kota'))
            ->assertForbidden();
    }

    public function test_sidebar_jadwal_memuat_dua_peta(): void
    {
        $this->actingAs($this->timSdm)
            ->get(route('jadwal-perjalanan'))
            ->assertOk()
            ->assertSee('Peta Dalam Kota &amp; Sekitarnya', false)
            ->assertSee('Peta Luar Kota')
            ->assertSee(route('jadwal-perjalanan.peta', 'dalam-kota'));
    }

    public function test_master_lokasi_menyimpan_koordinat(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)
            ->post(route('master.lokasi.store'), ['nama' => 'Tahuna', 'provinsi' => 'Sulawesi Utara', 'jenis' => 'luar_kota', 'lintang' => '3.6083', 'bujur' => '125.4979'])
            ->assertRedirect(route('master.lokasi'));

        $lokasi = LokasiTujuan::where('nama', 'Tahuna')->firstOrFail();
        $this->assertSame(3.6083, $lokasi->lintang);
        $this->assertSame(125.4979, $lokasi->bujur);

        // Lintang tanpa bujur ditolak; keduanya berpasangan.
        $this->actingAs($admin)
            ->from(route('master.lokasi'))
            ->post(route('master.lokasi.store'), ['nama' => 'Sorong', 'jenis' => 'luar_kota', 'lintang' => '-0.87'])
            ->assertSessionHasErrors('bujur');
    }
}
