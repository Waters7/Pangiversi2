<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenyusunNominatif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Daftar pengeluaran riil hanya untuk perjalanan luar kota lintas pulau.
 * Jalur lain memasukkan transport lokalnya ke rincian biaya, dan daftar
 * nominatifnya terbit begitu rincian biaya itu ditandatangani PPK.
 */
class DaftarRiilLintasPulauTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/77/2026';

    private User $timKeuangan;

    private User $ppk;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        $this->ppk = User::factory()->ppk()->create();
        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    private function usulan(JenisPerjadin $jalur): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => self::NO_TUGAS,
            'jenis_perjadin' => $jalur->value,
            'id_kategori_perjadin' => KategoriPerjadin::where('dalam_kota', $jalur === JenisPerjadin::DalamKota)->value('id'),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);
        $usulan->peserta()->create(['id_user' => $this->pelaksana->id, 'nama' => $this->pelaksana->nama, 'peran' => 'ketua']);

        return $usulan;
    }

    private function catatTransportLokal(Usulan $usulan, string $kategori): void
    {
        $this->actingAs($this->timKeuangan)
            ->post(route('keuangan.rincian.store', $usulan), [
                'kategori' => $kategori,
                'komponen' => 'Transport bandara',
                'volume' => 2,
                'satuan' => 'OK',
                'harga_satuan' => 150_000,
            ])
            ->assertSessionHasNoErrors();
    }

    /** Rincian biaya ditandatangani pelaksana lalu PPK lewat jalurnya. */
    private function sahkanRincian(Usulan $usulan): void
    {
        $peserta = $usulan->peserta()->first();
        $berkas = DaftarRiil::firstOrCreate(['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id], ['total_riil' => 0]);

        $berkas->kirimKePegawai();
        $berkas->jalurRincian()->setujui();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$usulan, $peserta, 'rincian']))
            ->assertSessionHas('success');
    }

    public function test_hanya_lintas_pulau_yang_memakai_daftar_riil(): void
    {
        $this->assertTrue($this->usulan(JenisPerjadin::LuarKota)->memakaiDaftarRiil());

        foreach ([JenisPerjadin::DalamKota, JenisPerjadin::LuarKotaDalamProvinsi, JenisPerjadin::Supervisi] as $jalur) {
            $this->assertFalse($this->usulan($jalur)->memakaiDaftarRiil(), $jalur->value);
        }
    }

    public function test_transport_lokal_jalur_lain_menjadi_baris_rincian_biaya(): void
    {
        $usulan = $this->usulan(JenisPerjadin::LuarKotaDalamProvinsi);

        $this->catatTransportLokal($usulan, KategoriBiaya::TransportLokalRincian->value);

        $baris = $usulan->fresh('keuangan.rincianBiaya')->keuangan->rincianBiaya->sole();

        $this->assertSame(KategoriBiaya::TransportLokalRincian, $baris->kategori);
        $this->assertSame(300_000.0, (float) $baris->jumlah);
        $this->assertSame(300_000.0, (float) $usulan->keuangan->fresh()->total);
        $this->assertFalse(DaftarRiil::where('id_usulan', $usulan->id)->where('total_riil', '>', 0)->exists());
    }

    /** Kiriman formulir lama tetap masuk dokumen yang berlaku bagi jalurnya. */
    public function test_kategori_transport_lokal_mengikuti_jalur_apa_pun_kirimannya(): void
    {
        $dalamKota = $this->usulan(JenisPerjadin::DalamKota);
        $this->catatTransportLokal($dalamKota, KategoriBiaya::TransportLokal->value);
        $this->assertSame(KategoriBiaya::TransportLokalRincian, $dalamKota->fresh('keuangan.rincianBiaya')->keuangan->rincianBiaya->sole()->kategori);

        $lintasPulau = $this->usulan(JenisPerjadin::LuarKota);
        $this->catatTransportLokal($lintasPulau, KategoriBiaya::TransportLokalRincian->value);
        $this->assertSame(0, $lintasPulau->fresh('keuangan.rincianBiaya')->keuangan->rincianBiaya->count());
        $this->assertSame(300_000.0, (float) DaftarRiil::firstWhere('id_usulan', $lintasPulau->id)->total_riil);
    }

    public function test_pilihan_kategori_dan_kartu_daftar_riil_mengikuti_jalur(): void
    {
        $dalamKota = $this->usulan(JenisPerjadin::DalamKota);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $dalamKota))
            ->assertOk()
            ->assertViewHas('kategoriBiaya', fn (array $pilihan) => isset($pilihan[KategoriBiaya::TransportLokalRincian->value])
                && ! isset($pilihan[KategoriBiaya::TransportLokal->value]))
            ->assertDontSee('data-transport-lokal-riil', false);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan(JenisPerjadin::LuarKota)))
            ->assertViewHas('kategoriBiaya', fn (array $pilihan) => $pilihan[KategoriBiaya::TransportLokal->value] === 'Transport Lokal → Daftar Riil')
            ->assertSee('data-transport-lokal-riil', false);
    }

    public function test_transport_lokal_tercetak_pada_rincian_dan_kolom_transport_nominatif(): void
    {
        $usulan = $this->usulan(JenisPerjadin::DalamKota);
        $this->catatTransportLokal($usulan, KategoriBiaya::TransportLokalRincian->value);
        $this->sahkanRincian($usulan);

        $data = null;
        View::composer('keuangan.cetak-rincian', function ($view) use (&$data): void {
            $data ??= $view->getData();
        });

        $this->actingAs($this->timKeuangan)->get(route('keuangan.cetak-rincian', $usulan))->assertOk();
        $html = view('keuangan.cetak-rincian', $data)->render();

        $this->assertStringContainsString('Transportasi Lokal', $html);
        $this->assertStringContainsString('Transport bandara', $html);
        $this->assertStringNotContainsString('Subtotal transportasi lokal', $html);
        $this->assertSame(300_000.0, $data['totalKeseluruhan']);

        $baris = app(PenyusunNominatif::class)->baris(self::NO_TUGAS)->sole();

        $this->assertSame(300_000.0, $baris['transport']);
        $this->assertSame(300_000.0, $baris['jumlah']);
    }

    /** Berkas pertanggungjawabannya boleh menyusul: rincian biaya sudah cukup. */
    public function test_rincian_ditandatangani_langsung_menerbitkan_daftar_nominatif_jalur_lain(): void
    {
        $usulan = $this->usulan(JenisPerjadin::DalamKota);
        $this->catatTransportLokal($usulan, KategoriBiaya::TransportLokalRincian->value);

        $this->assertNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));

        $this->sahkanRincian($usulan);

        $this->assertNotNull(DaftarNominatif::firstWhere('no_tugas', self::NO_TUGAS));
        $this->assertDatabaseHas('notifikasi', ['id_user' => $this->ppk->id, 'judul' => 'Daftar nominatif menunggu tanda tangan']);
    }

    public function test_transport_lokal_lama_jalur_lain_dipindahkan_bila_belum_disikapi(): void
    {
        $bebas = $this->berkasRiilLama($this->usulan(JenisPerjadin::DalamKota));

        $disetujui = $this->berkasRiilLama($this->usulan(JenisPerjadin::DalamKota));
        $disetujui->jalur()->setujui();

        $lintasPulau = $this->berkasRiilLama($this->usulan(JenisPerjadin::LuarKota));

        (require database_path('migrations/2026_11_05_003249_pindahkan_transport_lokal_ke_rincian_biaya.php'))->pindahkan();

        $baris = $bebas->usulan->fresh('keuangan.rincianBiaya')->keuangan->rincianBiaya->sole();
        $this->assertSame(KategoriBiaya::TransportLokalRincian, $baris->kategori);
        $this->assertSame('Taksi bandara', $baris->komponen);
        $this->assertSame(0.0, (float) $bebas->fresh()->total_riil);
        $this->assertSame(0, $bebas->rincian()->count());
        $this->assertSame(474_500.0, (float) $bebas->usulan->keuangan->fresh()->total);

        $this->assertSame(474_500.0, (float) $disetujui->fresh()->total_riil);
        $this->assertSame(474_500.0, (float) $lintasPulau->fresh()->total_riil);
    }

    private function berkasRiilLama(Usulan $usulan): DaftarRiil
    {
        /** @var PesertaUsulan $peserta */
        $peserta = $usulan->peserta()->first();

        $daftar = DaftarRiil::create(['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id, 'total_riil' => 474_500]);
        $daftar->rincian()->create(['urutan' => 1, 'uraian' => 'Taksi bandara', 'nominal' => 474_500, 'sumber' => 'manual']);

        return $daftar;
    }
}
