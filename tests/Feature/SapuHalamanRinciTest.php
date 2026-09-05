<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\ObrolanBantuan;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sapuan halaman rinci: yang membuka satu berkas, bukan daftar.
 *
 * Halaman daftar sering kosong pada basis data pengujian, sehingga galat
 * yang hanya muncul saat ada isinya lolos begitu saja. Di sini tiap halaman
 * rinci dibuka dengan berkas yang benar-benar ada, oleh peran yang berhak.
 */
class SapuHalamanRinciTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

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
            'no_tugas' => 'KP.03.01/F.XXXVIII/99/2026',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $peserta = $this->usulan->peserta()->create([
            'id_user' => $this->admin->id,
            'nama' => $this->admin->nama,
            'peran' => 'ketua',
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);

        DaftarRiil::create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $peserta->id,
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
            'maksud' => 'Uji sapuan halaman',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => today(),
            'tanggal_kembali' => today()->addDays(2),
            'lama_hari' => 3,
        ]);

        ObrolanBantuan::create([
            'id_pelapor' => $this->admin->id,
            'judul' => 'Uji sapuan halaman bantuan',
        ])->balas($this->admin, 'Isi laporan untuk keperluan sapuan halaman.');
    }

    /**
     * Halaman rinci beserta parameternya. Administrator dipakai karena ia
     * berhak membuka seluruhnya — yang diuji di sini keutuhan halamannya,
     * bukan kewenangannya.
     *
     * @return array<string, array{0: string, 1: array<int, mixed>}>
     */
    public static function halaman(): array
    {
        return [
            'detail usulan' => ['usulan.show', ['usulan']],
            'dokumen perjadin' => ['dokumen.show', ['usulan']],
            'laporan perjadin' => ['dokumen.laporan.edit', ['usulan']],
            'detail keuangan' => ['keuangan.detail', ['usulan']],
            'cetak rincian biaya' => ['keuangan.cetak-rincian', ['usulan']],
            'daftar riil' => ['daftar-riil.show', ['usulan']],
            'detail laporan' => ['laporan.show', ['usulan']],
            'detail spd' => ['spd.show', ['spd']],
            'cetak spd' => ['spd.cetak', ['spd']],
            'sunting spd' => ['spd.edit', ['spd']],
            'cetak nominatif' => ['laporan.nominatif.cetak', ['nominatif']],
            'obrolan bantuan' => ['bantuan.show', ['obrolan']],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_rinci_terbuka(string $rute, array $butuh): void
    {
        $parameter = array_map(fn (string $jenis) => match ($jenis) {
            'usulan' => $this->usulan->no_usulan,
            'spd' => SuratPerjalananDinas::firstOrFail()->id,
            'nominatif' => DaftarNominatif::firstOrFail()->id,
            'obrolan' => ObrolanBantuan::firstOrFail()->id,
        }, $butuh);

        $status = $this->actingAs($this->admin)->get(route($rute, $parameter))->getStatusCode();

        $this->assertContains(
            $status,
            [200, 302],
            "Halaman {$rute} menjawab {$status}."
        );
    }
}
