<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Kegiatan;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Ekspor Excel dihasilkan sendiri tanpa pustaka pihak ketiga, jadi yang
 * diperiksa bukan hanya kode HTTP-nya tetapi juga isi XML di dalam berkasnya.
 */
class EksporExcelTest extends TestCase
{
    use RefreshDatabase;

    private const TIPE_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Bongkar berkas xlsx hasil unduhan dan kembalikan XML lembar pertamanya.
     */
    private function isiSheet(TestResponse $response): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
        file_put_contents($berkas, $response->getContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($berkas) === true, 'Berkas xlsx tidak dapat dibuka sebagai arsip.');

        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($xml, 'Arsip xlsx tidak memuat lembar kerja.');

        $zip->close();
        unlink($berkas);

        return $xml;
    }

    private function perjalanan(array $atribut = []): Usulan
    {
        $unit = UnitKerja::factory()->create(['nama' => 'Jurusan Keperawatan']);

        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => 'Jakarta',
            'instansi' => 'Kementerian Kesehatan',
            'no_tugas' => 'OT.01.04/F.XXX/102/2026',
            'uraian' => 'Rapat Kerja Kementerian Kesehatan',
            'tanggal_mulai' => '2026-01-13',
            'tanggal_selesai' => '2026-01-15',
            'id_kegiatan' => Kegiatan::factory()->create(['nama' => 'Mengikuti rapat, seminar, lokakarya'])->id,
            'id_user' => User::factory()->create([
                'nama' => 'Sandra Tombokan',
                'id_unit' => $unit->id,
            ])->id,
            ...$atribut,
        ]);

        PesertaUsulan::factory()->create([
            'id_usulan' => $usulan->id,
            'id_user' => $usulan->id_user,
            'nama' => $usulan->user->nama,
        ]);

        $keuangan = Keuangan::factory()->create(['id_usulan' => $usulan->id, 'total' => 8_221_800]);

        $rincian = [
            [KategoriBiaya::Transport, 1, 4_749_300],
            [KategoriBiaya::TransportLokal, 1, 422_500],
            [KategoriBiaya::UangHarian, 3, 530_000],
            [KategoriBiaya::Penginapan, 2, 730_000],
        ];

        foreach ($rincian as [$kategori, $volume, $harga]) {
            RincianBiaya::factory()->create([
                'id_keuangan' => $keuangan->id,
                'kategori' => $kategori->value,
                'komponen' => $kategori->label(),
                'volume' => $volume,
                'harga_satuan' => $harga,
                'jumlah' => $volume * $harga,
            ]);
        }

        return $usulan->fresh();
    }

    // ── Daftar Nominatif dari menu Laporan ──

    public function test_laporan_menghasilkan_berkas_xlsx(): void
    {
        $this->perjalanan();

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('laporan.export-excel'))
            ->assertOk()
            ->assertHeader('content-type', self::TIPE_XLSX);
    }

    public function test_daftar_nominatif_memakai_judul_resmi(): void
    {
        $this->perjalanan();

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
                ->get(route('laporan.export-excel', ['bulan' => '2026-01']))
        );

        $this->assertStringContainsString('DAFTAR NOMINATIF PERJALANAN DINAS PEGAWAI', $xml);
        $this->assertStringContainsString('PADA POLITEKNIK KESEHATAN KEMENKES MANADO', $xml);
        $this->assertStringContainsString('Periode Januari 2026', $xml);
    }

    public function test_kepala_tabel_mengikuti_format_kppn(): void
    {
        $this->perjalanan();

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
                ->get(route('laporan.export-excel'))
        );

        foreach ([
            'NAMA / GOL', 'TEMPAT', 'ASAL', 'TUJUAN', 'LAMANYA', 'PERJALANAN',
            'MAKSUD PERJALANAN,', 'No. SPPD dan Tgl. SPPD / SURAT TUGAS',
            'TIKET', '(PP)', 'TRANSPORT', 'UANG HARIAN/SAKU',
            'UANG PENGINAPAN /', 'UANG PENYELENGGARA', 'HARI', 'BIAYA', 'JUMLAH', 'PEMBAYARAN',
        ] as $judul) {
            $this->assertStringContainsString($judul, $xml, "Kolom '{$judul}' tidak ditemukan.");
        }
    }

    public function test_biaya_dipecah_ke_kolom_yang_benar(): void
    {
        $this->perjalanan();

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
                ->get(route('laporan.export-excel'))
        );

        // Tiket, transport lokal, uang harian (3 x 530.000), penginapan (2 x 730.000).
        $this->assertStringContainsString('<v>4749300</v>', $xml);
        $this->assertStringContainsString('<v>422500</v>', $xml);
        $this->assertStringContainsString('<v>1590000</v>', $xml);
        $this->assertStringContainsString('<v>1460000</v>', $xml);
        $this->assertStringContainsString('<v>8221800</v>', $xml);
    }

    public function test_tiap_pegawai_menempati_tiga_baris(): void
    {
        $this->perjalanan();

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
                ->get(route('laporan.export-excel'))
        );

        $this->assertStringContainsString('Sandra Tombokan', $xml);
        $this->assertStringContainsString('13-15/01/2026', $xml);
        $this->assertStringContainsString('No. OT.01.04/F.XXX/102/2026', $xml);
        $this->assertStringContainsString('T O T A L', $xml);
    }

    public function test_penyaringan_bulan_dihormati(): void
    {
        $this->perjalanan();
        $this->perjalanan([
            'tanggal_mulai' => '2026-05-04',
            'tanggal_selesai' => '2026-05-06',
        ]);

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
                ->get(route('laporan.export-excel', ['bulan' => '2026-05']))
        );

        $this->assertStringContainsString('Periode Mei 2026', $xml);
        $this->assertStringContainsString('04-06/05/2026', $xml);
        $this->assertStringNotContainsString('13-15/01/2026', $xml);
    }

    public function test_usulan_yang_belum_disetujui_tidak_ikut(): void
    {
        $this->perjalanan(['status' => StatusUsulan::MenungguPpk->value]);

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
                ->get(route('laporan.export-excel'))
        );

        $this->assertStringNotContainsString('Sandra Tombokan', $xml);
    }

    public function test_pengusul_biasa_tidak_dapat_mengunduh_laporan(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::DosenTendik->value]))
            ->get(route('laporan.export-excel'))
            ->assertForbidden();
    }

    // ── Rekap jadwal perjalanan ──

    public function test_jadwal_menghasilkan_rekap_bulanan(): void
    {
        $this->perjalanan();

        $response = $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
            ->get(route('jadwal-perjalanan.ekspor', ['bulan' => '2026-01']))
            ->assertOk()
            ->assertHeader('content-type', self::TIPE_XLSX);

        $xml = $this->isiSheet($response);

        $this->assertStringContainsString('REKAP JADWAL PERJALANAN DINAS PEGAWAI', $xml);
        $this->assertStringContainsString('Periode Januari 2026', $xml);
        $this->assertStringContainsString('Sandra Tombokan', $xml);
        $this->assertStringContainsString('Jurusan Keperawatan', $xml);
        $this->assertStringContainsString('13/01/2026', $xml);
    }

    public function test_rekap_jadwal_memuat_ringkasan_per_unit(): void
    {
        $this->perjalanan();

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
                ->get(route('jadwal-perjalanan.ekspor', ['bulan' => '2026-01']))
        );

        $this->assertStringContainsString('REKAP PER UNIT KERJA', $xml);
        $this->assertStringContainsString('Jumlah pegawai', $xml);
    }

    public function test_rekap_jadwal_tidak_membocorkan_nominal_biaya(): void
    {
        $this->perjalanan();

        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
                ->get(route('jadwal-perjalanan.ekspor', ['bulan' => '2026-01']))
        );

        $this->assertStringNotContainsString('8221800', $xml);
        $this->assertStringNotContainsString('4749300', $xml);
    }

    public function test_bulan_kosong_tetap_menghasilkan_berkas(): void
    {
        $xml = $this->isiSheet(
            $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimSdm->value]))
                ->get(route('jadwal-perjalanan.ekspor', ['bulan' => '2030-09']))
        );

        $this->assertStringContainsString('Tidak ada keberangkatan pada periode ini.', $xml);
    }

    public function test_pengusul_biasa_tidak_dapat_mengunduh_rekap_jadwal(): void
    {
        $this->actingAs(User::factory()->create(['role' => PeranPengguna::DosenTendik->value]))
            ->get(route('jadwal-perjalanan.ekspor'))
            ->assertForbidden();
    }
}
