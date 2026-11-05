<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PengirimanBerkas;
use App\Services\PenyusunNominatif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Daftar nominatif terbit pada setiap jalur begitu dokumen yang berlaku pada
 * jalurnya ditandatangani PPK, walau berkas pertanggungjawabannya masih menyusul.
 */
class TerbitNominatifSemuaJalurTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/66/2026';

    private User $ppk;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppk = User::factory()->ppk()->create();
        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    /** Sampai dokumennya terkirim ke pelaksana lewat jalur kerja tim keuangan. */
    private function berkasTerkirim(JenisPerjadin $jalur, float $transportLokal = 0): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => self::NO_TUGAS,
            'jenis_perjadin' => $jalur->value,
            'id_kategori_perjadin' => KategoriPerjadin::where('dalam_kota', $jalur === JenisPerjadin::DalamKota)->value('id'),
        ]);

        $keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);
        $keuangan->rincianBiaya()->create([
            'kategori' => KategoriBiaya::UangHarian->value,
            'komponen' => 'Uang harian',
            'volume' => 2,
            'satuan' => 'OH',
            'harga_satuan' => 350_000,
            'jumlah' => 700_000,
        ]);

        $peserta = $usulan->peserta()->create(['id_user' => $this->pelaksana->id, 'nama' => $this->pelaksana->nama, 'peran' => 'ketua']);

        if ($transportLokal > 0) {
            DaftarRiil::create(['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id, 'total_riil' => $transportLokal]);
        }

        $this->validasiSeluruhNominal($usulan);
        $this->assertTrue(app(PengirimanBerkas::class)->kirimBilaSiap($usulan->fresh()));

        return $usulan->fresh();
    }

    private function tandaTangani(Usulan $usulan, string $jenis): void
    {
        $peserta = $usulan->peserta()->first();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$usulan, $peserta, $jenis]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$usulan, $peserta, $jenis]))
            ->assertSessionHas('success');
    }

    /** @return array<string, array{JenisPerjadin}> */
    public static function semuaJalur(): array
    {
        return array_combine(
            array_map(fn (JenisPerjadin $jalur) => $jalur->value, JenisPerjadin::cases()),
            array_map(fn (JenisPerjadin $jalur) => [$jalur], JenisPerjadin::cases()),
        );
    }

    #[DataProvider('semuaJalur')]
    public function test_terbit_begitu_rincian_ditandatangani_ppk_walau_berkas_belum_lengkap(JenisPerjadin $jalur): void
    {
        $usulan = $this->berkasTerkirim($jalur);

        $this->assertNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));

        $this->tandaTangani($usulan, 'rincian');

        $this->assertNotNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));
        $this->assertSame([$usulan->no_usulan], app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->pluck('usulan.no_usulan')->all());
        $this->assertDatabaseHas('notifikasi', ['id_user' => $this->ppk->id, 'judul' => 'Daftar nominatif menunggu tanda tangan']);
    }

    public function test_lintas_pulau_bertransport_lokal_menunggu_daftar_riil_disahkan(): void
    {
        $usulan = $this->berkasTerkirim(JenisPerjadin::LuarKota, transportLokal: 150_000);

        $this->tandaTangani($usulan, 'rincian');
        $this->assertNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));

        $this->tandaTangani($usulan, 'riil');
        $this->assertNotNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));
    }

    /** Daftar riil lama jalur lain tidak lagi menahan nominatifnya. */
    public function test_daftar_riil_lama_jalur_lain_tidak_menahan_nominatif(): void
    {
        $usulan = $this->berkasTerkirim(JenisPerjadin::DalamKota, transportLokal: 150_000);

        $this->tandaTangani($usulan, 'rincian');

        $this->assertNotNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));
        $this->assertSame(150_000.0, app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->sole()['transport']);
    }

    /** Rincian yang disahkan sebelum aturan ini berlaku tidak tertinggal. */
    public function test_yang_sudah_disahkan_lebih_dulu_terbit_saat_list_dibuka(): void
    {
        $usulan = $this->berkasTerkirim(JenisPerjadin::LuarKota);
        $berkas = DaftarRiil::firstWhere('id_usulan', $usulan->id);
        $berkas->jalurRincian()->setujui();
        $berkas->jalurRincian()->tandaTangani($this->ppk);

        $this->assertNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]))
            ->get(route('laporan.nominatif'))
            ->assertOk()
            ->assertSee(self::NO_TUGAS);
    }
}
