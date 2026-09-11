<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\LaporanPerjadin;
use App\Models\PesertaUsulan;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use App\Services\KertasCetak;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Satuan kerja mencetak di kertas folio (foolscap), bukan A4. Setiap
 * dokumen yang keluar dari aplikasi harus berukuran itu — satu saja yang
 * tertinggal di A4 akan tercetak dengan margin bawah yang menganga atau
 * terpotong, tergantung pengaturan mesin cetaknya.
 */
class UkuranKertasCetakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Folio menurut dompdf: 8,5 × 13 inci = 612 × 936 pt.
     */
    private const TEGAK = '/MediaBox [0.000 0.000 612.000 936.000]';

    private const MENDATAR = '/MediaBox [0.000 0.000 936.000 612.000]';

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(KategoriPerjadinSeeder::class);

        $this->admin = User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
        $ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->admin->id,
            'status' => StatusUsulan::Disetujui->value,
            'no_tugas' => 'KP.03.01/F.XXXVIII/77/2026',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->admin->id,
            'nama' => $this->admin->nama,
            'peran' => 'ketua',
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);

        DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => 474_500,
            'ditandatangani_at' => now(),
            'id_ppk' => $ppk->id,
            'rincian_ditandatangani_at' => now(),
            'rincian_id_ppk' => $ppk->id,
        ]);

        DaftarNominatif::create([
            'no_tugas' => $this->usulan->no_tugas,
            'id_ppk' => $ppk->id,
            'ditandatangani_at' => now(),
            'dikirim_at' => now(),
        ]);

        SuratPerjalananDinas::create([
            'id_usulan' => $this->usulan->id,
            'id_pembuat' => $this->admin->id,
            'dikeluarkan_di' => 'Manado',
            'tanggal_surat' => today(),
            'maksud' => 'Uji ukuran kertas',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => today(),
            'tanggal_kembali' => today()->addDays(2),
            'lama_hari' => 3,
        ]);

        // Laporannya sudah dibuat saat berkas dilengkapi; cetakannya baru
        // terbuka setelah dinyatakan selesai.
        LaporanPerjadin::updateOrCreate(
            ['id_usulan' => $this->usulan->id],
            ['diselesaikan_at' => now()],
        );
    }

    /**
     * Seluruh dokumen cetak beserta orientasinya.
     *
     * @return array<string, array{0: string, 1: list<string>, 2: string}>
     */
    public static function dokumen(): array
    {
        return [
            'surat perjalanan dinas' => ['spd.cetak', ['spd'], self::TEGAK],
            'usulan perjadin' => ['persetujuan.export', ['usulan'], self::TEGAK],
            'format laporan perjadin' => ['dokumen.format-laporan', ['usulan'], self::TEGAK],
            'laporan perjadin' => ['dokumen.laporan.cetak', ['usulan'], self::TEGAK],
            'rincian biaya' => ['keuangan.cetak-rincian', ['usulan'], self::TEGAK],
            'daftar pengeluaran riil' => ['daftar-riil.cetak', ['usulan', 'peserta'], self::TEGAK],
            'daftar nominatif' => ['laporan.nominatif.cetak', ['nominatif'], self::MENDATAR],
        ];
    }

    /**
     * @param  list<string>  $butuh
     */
    #[DataProvider('dokumen')]
    public function test_dokumen_tercetak_di_kertas_folio(string $rute, array $butuh, string $ukuran): void
    {
        $parameter = array_map(fn (string $jenis) => match ($jenis) {
            'usulan' => $this->usulan->no_usulan,
            'peserta' => $this->peserta->id,
            'spd' => SuratPerjalananDinas::firstOrFail()->id,
            'nominatif' => DaftarNominatif::firstOrFail()->id,
        }, $butuh);

        $balasan = $this->actingAs($this->admin)
            ->get(route($rute, $parameter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString($ukuran, $this->isiPdf($balasan), "Dokumen {$rute} tidak berukuran folio.");
    }

    public function test_ukuran_bakunya_folio(): void
    {
        $this->assertSame('folio', KertasCetak::UKURAN);
    }

    /**
     * Balasan unduhan berupa aliran berkas; isinya dibaca dari sana.
     */
    private function isiPdf(TestResponse $balasan): string
    {
        ob_start();
        $balasan->sendContent();

        return (string) ob_get_clean();
    }
}
