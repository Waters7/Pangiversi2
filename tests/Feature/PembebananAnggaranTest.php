<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\AuditLog;
use App\Models\DaftarNominatif;
use App\Models\KategoriPembiayaan;
use App\Models\Keuangan;
use App\Models\PaguAkun;
use App\Models\PaguTimKerja;
use App\Models\TahunAnggaran;
use App\Models\TimKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\RingkasanEksekutif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Menu Pembebanan Anggaran: sumber dana, akun, dan tim kerja tiap daftar
 * nominatif pada tahap mana pun, dengan pilihan tim kerja menurut pagu tim
 * kerja pada akunnya.
 */
class PembebananAnggaranTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private TahunAnggaran $tahun;

    private AkunPembiayaan $akunBiasa;

    private AkunPembiayaan $akunBlu;

    private TimKerja $penjaminanMutu;

    private TimKerja $kemahasiswaan;

    private int $rm;

    private int $blu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->tahun = TahunAnggaran::factory()->aktif()->create(['tahun' => 2026]);
        $this->rm = KategoriPembiayaan::where('kode', 'RM')->value('id');
        $this->blu = KategoriPembiayaan::where('kode', 'BLU')->value('id');

        $this->akunBiasa = AkunPembiayaan::firstOrCreate(['kode' => '524111'], ['nama' => 'Belanja Perjalanan Dinas Biasa']);
        $this->akunBlu = AkunPembiayaan::firstOrCreate(['kode' => '525115'], ['nama' => 'Belanja Perjalanan']);

        $this->penjaminanMutu = TimKerja::factory()->create(['nama' => 'Pusat Penjaminan Mutu', 'urutan' => 1]);
        $this->kemahasiswaan = TimKerja::factory()->create(['nama' => 'Tim Kemahasiswaan dan Alumni', 'urutan' => 2]);

        PaguAkun::factory()->create(['id_tahun_anggaran' => $this->tahun->id, 'id_akun_pembiayaan' => $this->akunBiasa->id, 'id_kategori_pembiayaan' => $this->rm, 'pagu' => 437_132_000]);
        $this->alokasi($this->akunBiasa, $this->rm, $this->penjaminanMutu, 41_564_000);
        $this->alokasi($this->akunBlu, $this->blu, $this->kemahasiswaan, 176_150_000);
        $this->alokasi($this->akunBlu, $this->rm, $this->kemahasiswaan, 254_582_000);
    }

    private function alokasi(AkunPembiayaan $akun, int $sumber, TimKerja $tim, int $pagu): void
    {
        PaguTimKerja::factory()->create([
            'id_tahun_anggaran' => $this->tahun->id,
            'id_akun_pembiayaan' => $akun->id,
            'id_kategori_pembiayaan' => $sumber,
            'id_tim_kerja' => $tim->id,
            'pagu' => $pagu,
        ]);
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function nominatif(string $noTugas, array $isian = []): DaftarNominatif
    {
        return DaftarNominatif::create(['no_tugas' => $noTugas, 'tanggal_tugas' => '2026-10-01'] + $isian);
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function tetapkan(DaftarNominatif $nominatif, array $isian, ?User $oleh = null): TestResponse
    {
        return $this->actingAs($oleh ?? $this->admin)->put(route('pembebanan-anggaran.update', $nominatif), $isian);
    }

    public function test_menu_hanya_terbuka_bagi_pemegang_hak(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]))
            ->get(route('pembebanan-anggaran'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran'))
            ->assertOk()
            ->assertSee(route('pembebanan-anggaran'), false);
    }

    public function test_daftar_pada_setiap_tahap_tampil_dan_dapat_disaring(): void
    {
        $this->nominatif('ST/MENUNGGU/2026');
        $this->nominatif('ST/BELUM-BERTIM/2026', ['ditandatangani_at' => now(), 'dikirim_at' => now()]);
        $this->nominatif('ST/LENGKAP/2026', ['ditandatangani_at' => now(), 'dikirim_at' => now(), 'id_akun_pembiayaan' => $this->akunBiasa->id, 'id_tim_kerja' => $this->penjaminanMutu->id]);

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran'))
            ->assertSee(['ST/MENUNGGU/2026', 'ST/BELUM-BERTIM/2026', 'ST/LENGKAP/2026'])
            ->assertSee('data-pilihan-tim', false)
            ->assertSee('Ditandatangani &amp; Diterima Tim Keuangan', false);

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran', ['tahap' => 'menunggu']))
            ->assertSee('ST/MENUNGGU/2026')
            ->assertDontSee(['ST/BELUM-BERTIM/2026', 'ST/LENGKAP/2026']);

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran', ['tahap' => 'diterima']))
            ->assertSee(['ST/BELUM-BERTIM/2026', 'ST/LENGKAP/2026'])
            ->assertDontSee('ST/MENUNGGU/2026');

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran', ['pembebanan' => 'belum']))
            ->assertSee(['ST/MENUNGGU/2026', 'ST/BELUM-BERTIM/2026'])
            ->assertDontSee('ST/LENGKAP/2026');
    }

    public function test_nama_pelaksana_dan_kegiatan_tampil_dan_dapat_dicari(): void
    {
        $ppk = User::factory()->ppk()->create();
        $pelaksana = User::factory()->create(['nama' => 'Rina Kartika Dewi']);
        $usulan = Usulan::factory()->create([
            'id_user' => $pelaksana->id,
            'no_tugas' => 'ST/KEGIATAN/2026',
            'status' => StatusUsulan::Disetujui->value,
            'uraian' => 'Mengikuti IHT OSCE AIPTLMI Regional 7',
            'id_spd' => null,
        ]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);
        $this->tandatanganiBerkas($this->lengkapiPertanggungjawaban($usulan)->fresh(), $ppk);
        $this->nominatif('ST/KEGIATAN/2026');
        $this->nominatif('ST/LAIN/2026');

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran'))
            ->assertSeeInOrder(['ST/KEGIATAN/2026', 'Kegiatan:', 'Mengikuti IHT OSCE AIPTLMI Regional 7', 'Pelaksana:', 'Rina Kartika Dewi']);

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran', ['cari' => 'rina kartika']))
            ->assertSee('ST/KEGIATAN/2026')
            ->assertDontSee('ST/LAIN/2026');

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran', ['cari' => 'OSCE']))
            ->assertSee('ST/KEGIATAN/2026')
            ->assertDontSee('ST/LAIN/2026');
    }

    public function test_akun_dan_tim_kerja_ditetapkan_sebelum_daftar_dikirim(): void
    {
        $nominatif = $this->nominatif('ST/AWAL/2026', ['ditandatangani_at' => now()]);

        $this->tetapkan($nominatif, ['id_akun_pembiayaan' => $this->akunBiasa->id, 'id_tim_kerja' => $this->penjaminanMutu->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $nominatif->refresh();
        $this->assertSame($this->akunBiasa->id, $nominatif->id_akun_pembiayaan);
        $this->assertSame($this->penjaminanMutu->id, $nominatif->id_tim_kerja);
        // Sumber dana terisi dari satu-satunya pagu tim itu pada akun ini.
        $this->assertSame($this->rm, $nominatif->id_kategori_pembiayaan);
        $this->assertDatabaseHas(AuditLog::class, ['aksi' => AuditLog::AKSI_BIAYA, 'objek' => 'pembebanan-nominatif']);
    }

    /** Formulirnya hanya menyebut nama tim; angkanya ada pada ringkasan pagu dan realisasi. */
    public function test_pilihan_tim_kerja_tidak_memuat_nominal(): void
    {
        $this->nominatif('KP.03.01/TIM/1/2026');

        $pilihan = fn (array $alokasi) => $alokasi['pilihan'][$this->tahun->id][$this->akunBiasa->id];

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran'))
            ->assertOk()
            ->assertViewHas('alokasi', fn (array $alokasi) => $pilihan($alokasi) === [
                ['tim' => $this->penjaminanMutu->id, 'nama' => 'Pusat Penjaminan Mutu', 'sumber' => $this->rm],
            ])
            ->assertDontSee('"pagu":', false);
    }

    public function test_tim_kerja_harus_berpagu_pada_akun_dan_sumber_dananya(): void
    {
        $nominatif = $this->nominatif('ST/SALAH/2026');

        $this->tetapkan($nominatif, ['id_tim_kerja' => $this->penjaminanMutu->id])
            ->assertSessionHasErrors(['id_tim_kerja' => 'Pilih akun pembiayaan sebelum tim kerjanya.']);
        $this->tetapkan($nominatif, ['id_akun_pembiayaan' => $this->akunBlu->id, 'id_tim_kerja' => $this->penjaminanMutu->id])
            ->assertSessionHasErrors(['id_tim_kerja' => 'Tim kerja itu tidak berpagu pada akun yang dipilih.']);
        $this->tetapkan($nominatif, ['id_akun_pembiayaan' => $this->akunBlu->id, 'id_tim_kerja' => $this->kemahasiswaan->id])
            ->assertSessionHasErrors('id_tim_kerja');
        $this->tetapkan($nominatif, ['id_kategori_pembiayaan' => KategoriPembiayaan::where('kode', 'LN')->value('id'), 'id_akun_pembiayaan' => $this->akunBlu->id, 'id_tim_kerja' => $this->kemahasiswaan->id])
            ->assertSessionHasErrors(['id_tim_kerja' => 'Tim kerja itu tidak berpagu dari sumber dana yang dipilih pada akun ini.']);
        $this->assertNull($nominatif->fresh()->id_tim_kerja);

        // Tim berpagu dua sumber dana pada akun yang sama: sumber dananya dipilih.
        $this->tetapkan($nominatif, ['id_kategori_pembiayaan' => $this->rm, 'id_akun_pembiayaan' => $this->akunBlu->id, 'id_tim_kerja' => $this->kemahasiswaan->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->kemahasiswaan->id, $nominatif->fresh()->id_tim_kerja);
    }

    public function test_daftar_nominatif_yang_belum_dikirim_hanya_dapat_ditetapkan_pemegang_hak(): void
    {
        $nominatif = $this->nominatif('ST/TERTAHAN/2026', ['ditandatangani_at' => now()]);
        $timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $this->actingAs($timKeuangan)
            ->put(route('laporan.nominatif.akun', $nominatif), ['id_akun_pembiayaan' => $this->akunBiasa->id])
            ->assertForbidden();
        $this->actingAs($timKeuangan)
            ->get(route('laporan.nominatif'))
            ->assertDontSee('data-ke-pembebanan', false);

        $this->actingAs($this->admin)
            ->get(route('laporan.nominatif'))
            ->assertSee('data-ke-pembebanan', false);

        // Setelah dikirim, tim keuangan menetapkan tim kerjanya di List Daftar Nominatif.
        $nominatif->update(['dikirim_at' => now()]);
        $this->actingAs($timKeuangan)
            ->put(route('laporan.nominatif.akun', $nominatif), ['id_akun_pembiayaan' => $this->akunBiasa->id, 'id_tim_kerja' => $this->penjaminanMutu->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->penjaminanMutu->id, $nominatif->fresh()->id_tim_kerja);
    }

    public function test_realisasi_dihitung_per_tim_kerja(): void
    {
        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'ST/REALISASI/2026',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-07',
        ]);
        Keuangan::factory()->create(['id_usulan' => $usulan->id, 'total' => 8_558_568]);
        $this->nominatif('ST/REALISASI/2026', [
            'id_kategori_pembiayaan' => $this->rm,
            'id_akun_pembiayaan' => $this->akunBiasa->id,
            'id_tim_kerja' => $this->penjaminanMutu->id,
        ]);

        $akun = app(RingkasanEksekutif::class)->anggaranPerTimKerja(2026)->firstWhere('kode', '524111');
        $tim = $akun['tim']->firstWhere('nama', 'Pusat Penjaminan Mutu');

        $this->assertSame(437_132_000.0, $akun['pagu']);
        $this->assertSame(8_558_568.0, $tim['realisasi']);
        $this->assertSame(41_564_000.0 - 8_558_568.0, $tim['sisa']);

        $this->actingAs($this->admin)
            ->get(route('pembebanan-anggaran'))
            ->assertSeeInOrder(['Pagu dan Realisasi per Tim Kerja', '524111', 'Pusat Penjaminan Mutu', 'Rp 41.564.000', 'Rp 8.558.568']);
    }

    public function test_pagu_tim_kerja_ta_2026_mengikuti_daftar_anggaran_fix(): void
    {
        foreach (['524119' => 'Belanja Perjalanan Dinas Paket Meeting Luar Kota'] as $kode => $nama) {
            AkunPembiayaan::firstOrCreate(['kode' => $kode], ['nama' => $nama]);
        }
        PaguTimKerja::query()->delete();
        TimKerja::query()->delete();

        $migrasi = require database_path('migrations/2026_11_03_073515_create_tim_kerja_dan_pagu_tim_kerja_table.php');
        $migrasi->isiPaguTimKerja();

        $jumlah = fn (string $kode) => (float) PaguTimKerja::whereHas('akun', fn ($q) => $q->where('kode', $kode))->sum('pagu');

        $this->assertSame(18, TimKerja::count());
        $this->assertSame(23, PaguTimKerja::count());
        $this->assertSame(437_132_000.0, $jumlah('524111'));
        $this->assertSame(144_048_000.0, $jumlah('524119'));
        $this->assertSame(1_897_354_000.0, $jumlah('525115'));
    }
}
