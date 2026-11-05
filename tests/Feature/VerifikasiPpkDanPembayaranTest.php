<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman verifikasi PPK mendahulukan yang sudah dapat ditandatangani, dan
 * menu Persetujuan menyebut sampai mana bendahara membayar daftar nominatif.
 */
class VerifikasiPpkDanPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppk = User::factory()->ppk()->create();
    }

    private function berkas(string $tanggal, bool $disetujuiPelaksana, int $totalRiil = 150_000): Usulan
    {
        $usulan = Usulan::factory()->create([
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
        ]);

        $keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);
        $keuangan->rincianBiaya()->create([
            'kategori' => KategoriBiaya::UangHarian->value,
            'komponen' => 'Uang harian',
            'volume' => 1,
            'satuan' => 'OH',
            'harga_satuan' => 350_000,
            'jumlah' => 350_000,
            'divalidasi_at' => now(),
        ]);

        $peserta = $usulan->peserta()->create(['id_user' => $usulan->id_user, 'nama' => 'Pelaksana', 'peran' => 'ketua']);
        $daftar = DaftarRiil::create(['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id, 'total_riil' => $totalRiil, 'divalidasi_at' => now()]);
        $daftar->kirimKePegawai();

        if ($disetujuiPelaksana) {
            $daftar->jalur()->setujui();
            $daftar->jalurRincian()->setujui();
        }

        return $usulan;
    }

    public function test_rincian_yang_dapat_ditandatangani_tampil_paling_atas_berapa_pun_umurnya(): void
    {
        $lama = $this->berkas('2026-02-10', disetujuiPelaksana: true);
        $baru = $this->berkas('2026-10-10', disetujuiPelaksana: false);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.rincian-biaya'))
            ->assertOk()
            ->assertSeeInOrder(['Siap Ditandatangani', $lama->no_usulan, 'Oktober 2026', $baru->no_usulan]);
    }

    public function test_daftar_riil_yang_dapat_ditandatangani_berkumpul_paling_atas(): void
    {
        $lama = $this->berkas('2026-02-10', disetujuiPelaksana: true);
        $baru = $this->berkas('2026-10-10', disetujuiPelaksana: true);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.daftar-riil'))
            ->assertOk()
            ->assertSeeInOrder(['Siap Ditandatangani', $baru->no_usulan, $lama->no_usulan])
            ->assertDontSee('Oktober 2026');
    }

    public function test_tanpa_yang_siap_kelompoknya_tidak_tampil(): void
    {
        $this->berkas('2026-10-10', disetujuiPelaksana: false);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.rincian-biaya'))
            ->assertOk()
            ->assertDontSee('Siap Ditandatangani');
    }

    public function test_menu_persetujuan_menyebut_status_pembayaran_tiap_pelaksana(): void
    {
        $noTugas = 'KP.03.01/F.XXXVIII/44/2026';

        $bayar = [
            'Maya Sari' => Keuangan::STATUS_LUNAS,
            'Rina Kartika' => Keuangan::STATUS_SEBAGIAN,
            'Dewi Lestari' => Keuangan::STATUS_BELUM,
        ];

        foreach ($bayar as $nama => $status) {
            $usulan = Usulan::factory()->create([
                'id_user' => User::factory()->create(['nama' => $nama])->id,
                'no_tugas' => $noTugas,
                'status' => StatusUsulan::Disetujui->value,
            ]);

            Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id, 'status' => $status]);
            $this->tandatanganiBerkas($this->lengkapiPertanggungjawaban($usulan), $this->ppk);
        }

        $daftar = DaftarNominatif::create(['no_tugas' => $noTugas, 'id_ppk' => $this->ppk->id, 'ditandatangani_at' => now(), 'dikirim_at' => now()]);

        foreach ([route('persetujuan.nominatif'), route('persetujuan.nominatif.detail', $daftar)] as $halaman) {
            $this->actingAs($this->ppk)
                ->get($halaman)
                ->assertOk()
                ->assertSee('data-pembayaran-nominatif', false)
                ->assertSee('1 dari 3 pelaksana lunas')
                ->assertSeeInOrder(['Maya Sari', 'Lunas 100%'])
                ->assertSeeInOrder(['Rina Kartika', 'Uang Muka Terbayar'])
                ->assertSeeInOrder(['Dewi Lestari', 'Belum Bayar']);
        }
    }
}
