<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AkunPembiayaan;
use App\Models\DaftarNominatif;
use App\Models\Keuangan;
use App\Models\TimKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Arsip Daftar Riil, Arsip Rincian Lengkap, dan List Daftar Nominatif dapat
 * diekspor ke Excel, seluruh baris pada saringan yang sedang dipilih.
 */
class EksporArsipLaporanTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/55/2026';

    private User $ppk;

    private User $timKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppk = User::factory()->ppk()->create();
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
    }

    private function perjalanan(string $nama, string $noTugas = self::NO_TUGAS, string $tanggal = '2026-08-10'): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => User::factory()->create(['nama' => $nama])->id,
            'no_tugas' => $noTugas,
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => 'Jakarta',
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        $usulan = $this->lengkapiPertanggungjawaban($usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($usulan);
        $this->tandatanganiBerkas($usulan->fresh(), $this->ppk);

        return $usulan->fresh();
    }

    private function isiSheet(TestResponse $balasan): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
        file_put_contents($berkas, $balasan->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas) === true, 'Berkas xlsx tidak dapat dibuka.');

        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');

        $zip->close();
        unlink($berkas);

        return $xml;
    }

    private function unduh(string $rute, array $saringan = []): TestResponse
    {
        return $this->actingAs($this->timKeuangan)
            ->get(route($rute, $saringan))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_arsip_daftar_riil_memuat_seluruh_baris_saringan(): void
    {
        $usulan = $this->perjalanan('Maya Sari');
        $lain = $this->perjalanan('Rina Kartika', 'KP.03.01/F.XXXVIII/56/2026', '2026-03-10');

        $semua = $this->isiSheet($this->unduh('laporan.daftar-riil.ekspor'));

        $this->assertStringContainsString('ARSIP DAFTAR PENGELUARAN RIIL', $semua);
        $this->assertStringContainsString($usulan->no_usulan, $semua);
        $this->assertStringContainsString($lain->no_usulan, $semua);

        $agustus = $this->isiSheet($this->unduh('laporan.daftar-riil.ekspor', ['tahun' => 2026, 'bulan' => 8]));

        $this->assertStringContainsString($usulan->no_usulan, $agustus);
        $this->assertStringNotContainsString($lain->no_usulan, $agustus);
    }

    public function test_arsip_rincian_lengkap_memuat_berkas_yang_lengkap_saja(): void
    {
        $lengkap = $this->perjalanan('Maya Sari');

        $belum = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);

        $isi = $this->isiSheet($this->unduh('laporan.rincian-lengkap.ekspor'));

        $this->assertStringContainsString('ARSIP RINCIAN BIAYA LENGKAP TANDA TANGAN', $isi);
        $this->assertStringContainsString($lengkap->no_usulan, $isi);
        $this->assertStringNotContainsString($belum->no_usulan, $isi);
    }

    public function test_list_nominatif_memuat_tiap_pelaksana_beserta_pembebanan_dan_pembayarannya(): void
    {
        $this->perjalanan('Maya Sari');
        $this->perjalanan('Rina Kartika');

        DaftarNominatif::create([
            'no_tugas' => self::NO_TUGAS,
            'id_akun_pembiayaan' => AkunPembiayaan::firstWhere('kode', '524111')->id,
            'id_tim_kerja' => TimKerja::factory()->create(['nama' => 'Tim OSDM'])->id,
        ]);

        $isi = $this->isiSheet($this->unduh('laporan.nominatif.ekspor'));

        foreach (['LIST DAFTAR NOMINATIF', self::NO_TUGAS, 'Maya Sari', 'Rina Kartika', '524111', 'Tim OSDM', 'Belum Bayar'] as $teks) {
            $this->assertStringContainsString($teks, $isi);
        }
    }

    public function test_daftar_tanpa_pelaksana_tetap_tercantum_dan_saringan_status_berlaku(): void
    {
        DaftarNominatif::create(['no_tugas' => 'KP.BELUM/1/2026']);
        DaftarNominatif::create(['no_tugas' => 'KP.DITERIMA/2/2026', 'ditandatangani_at' => now(), 'dikirim_at' => now()]);

        $semua = $this->isiSheet($this->unduh('laporan.nominatif.ekspor'));

        $this->assertStringContainsString('KP.BELUM/1/2026', $semua);
        $this->assertStringContainsString('KP.DITERIMA/2/2026', $semua);

        $menunggu = $this->isiSheet($this->unduh('laporan.nominatif.ekspor', ['status' => 'menunggu']));

        $this->assertStringContainsString('KP.BELUM/1/2026', $menunggu);
        $this->assertStringNotContainsString('KP.DITERIMA/2/2026', $menunggu);
    }

    public function test_tombol_ekspor_membawa_saringan_yang_sedang_dipilih(): void
    {
        foreach (['laporan.daftar-riil', 'laporan.rincian-lengkap', 'laporan.nominatif'] as $rute) {
            $this->actingAs($this->timKeuangan)
                ->get(route($rute, ['tahun' => 2026]))
                ->assertOk()
                ->assertSee(route($rute.'.ekspor', ['tahun' => 2026]), false);
        }
    }

    public function test_peran_tanpa_akses_arsip_tidak_dapat_mengunduh(): void
    {
        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        foreach (['laporan.daftar-riil.ekspor', 'laporan.rincian-lengkap.ekspor', 'laporan.nominatif.ekspor'] as $rute) {
            $this->actingAs($pegawai)->get(route($rute))->assertForbidden();
        }
    }
}
