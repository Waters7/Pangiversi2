<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Database\Seeders\KategoriPerjadinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Halaman yang dibuka tiap hari tidak boleh bertambah berat seiring
 * arsipnya bertambah.
 *
 * Dua halaman ini pernah menambah kueri untuk setiap baris: dashboard
 * memeriksa kelengkapan berkas per perjalanan, dan verifikasi nominatif
 * menyusun barisnya per surat tugas. Keduanya melambat tiap tahun tanpa
 * pernah kembali ringan, dan itu tidak terlihat sampai ada yang mengeluh.
 *
 * Pengujian di sini membuka halaman yang sama pada dua ukuran data, lalu
 * menuntut jumlah kuerinya tidak ikut membesar.
 */
class BebanHalamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(KategoriPerjadinSeeder::class);
    }

    /**
     * Satu perjalanan yang berkasnya sudah tuntas dan kedua dokumen
     * pertanggungjawabannya sudah ditandatangani PPK — keadaan yang membuat
     * daftar nominatifnya terbit sendiri saat halaman verifikasi dibuka.
     *
     * Sengaja dalam kota, karena kelengkapannya cukup SPPD, nota transport,
     * dan laporan; menyiapkan tiket dan penginapan tidak menambah apa pun
     * bagi yang diuji di sini.
     */
    private function perjalanan(User $pemilik, int $urutan): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $pemilik->id,
            'status' => StatusUsulan::Disetujui->value,
            'id_kategori_perjadin' => KategoriPerjadin::where('dalam_kota', true)->value('id'),
            'no_tugas' => 'KP.03.01/F.XXXVIII/'.$urutan.'/'.today()->year,
            'tanggal_mulai' => today()->subDays(10)->toDateString(),
            'tanggal_selesai' => today()->subDays(8)->toDateString(),
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);
        Dokumen::create(['id_usulan' => $usulan->id, 'sppd' => 'demo/sppd.pdf']);

        $usulan->notaTransport()->create([
            'urutan' => 1,
            'nominal' => 75_000,
            'keterangan' => 'Kantor ke lokasi kegiatan',
            'bukti' => 'demo/nota.pdf',
        ]);

        $usulan->laporan()->create(['diselesaikan_at' => now()]);

        $peserta = $usulan->peserta()->create([
            'id_user' => $pemilik->id,
            'nama' => $pemilik->nama,
            'peran' => 'ketua',
        ]);

        DaftarRiil::create([
            'id_usulan' => $usulan->id,
            'id_peserta' => $peserta->id,
            'total_riil' => 150_000,
            'ditandatangani_at' => now(),
            'rincian_ditandatangani_at' => now(),
        ]);

        return $usulan;
    }

    /**
     * Jumlah kueri yang dijalankan satu permintaan halaman.
     */
    private function kueri(User $sebagai, string $alamat): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($sebagai)->get($alamat)->assertOk();

        $jumlah = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $jumlah;
    }

    /**
     * Bandingkan beban halaman pada dua ukuran data.
     *
     * Tiap pengukuran didahului satu kali buka tanpa dihitung: halaman
     * verifikasi menerbitkan daftar nominatif yang sudah layak saat dibuka,
     * dan itu pekerjaan sekali seumur berkas — bukan beban yang ditanggung
     * setiap kali halamannya dilihat.
     *
     * @param  callable(int): void  $tambah
     * @return array{0: int, 1: int}
     */
    private function bandingkan(User $sebagai, string $alamat, callable $tambah): array
    {
        foreach (range(1, 5) as $i) {
            $tambah($i);
        }

        $this->actingAs($sebagai)->get($alamat)->assertOk();
        $sedikit = $this->kueri($sebagai, $alamat);

        foreach (range(6, 20) as $i) {
            $tambah($i);
        }

        $this->actingAs($sebagai)->get($alamat)->assertOk();

        return [$sedikit, $this->kueri($sebagai, $alamat)];
    }

    public function test_dashboard_tidak_menambah_kueri_per_perjalanan(): void
    {
        $pengguna = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        [$sedikit, $banyak] = $this->bandingkan(
            $pengguna,
            route('dashboard'),
            fn (int $i) => $this->perjalanan($pengguna, $i),
        );

        // Empat kali lipat datanya; bebannya tidak boleh ikut naik.
        $this->assertLessThanOrEqual(
            $sedikit,
            $banyak,
            "Dashboard menjalankan {$sedikit} kueri pada 5 perjalanan dan {$banyak} pada 20 — "
            .'relasinya belum dimuat sekaligus.',
        );
    }

    public function test_verifikasi_nominatif_tidak_menambah_kueri_per_surat_tugas(): void
    {
        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);
        $pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        [$sedikit, $banyak] = $this->bandingkan(
            $ppk,
            route('persetujuan.nominatif'),
            fn (int $i) => $this->perjalanan($pelaksana, $i),
        );

        // Halamannya dibatasi 10 daftar, dan barisnya dimuat sekali untuk
        // seluruh surat tugas pada halaman berjalan. Selisih kecil masih
        // wajar karena halaman pertama ikut terisi penuh; yang tidak boleh
        // adalah bertambah sebanding dengan datanya.
        $this->assertLessThan(
            $sedikit * 2,
            $banyak,
            "Verifikasi nominatif menjalankan {$sedikit} kueri pada 5 surat tugas dan {$banyak} pada 20 — "
            .'barisnya masih disusun satu per satu.',
        );
    }

    /** Halaman verifikasi nominatif memang berhalaman, bukan menampilkan semuanya. */
    public function test_verifikasi_nominatif_berhalaman(): void
    {
        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);
        $pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        foreach (range(1, 14) as $i) {
            $this->perjalanan($pelaksana, $i);
        }

        $halaman = $this->actingAs($ppk)
            ->get(route('persetujuan.nominatif'))
            ->assertOk()
            ->viewData('halaman');

        $this->assertSame(10, $halaman->perPage());
        $this->assertTrue($halaman->hasPages(), 'Daftar sepanjang ini seharusnya terbagi halaman.');
    }

    /**
     * Perjalanan lama yang sudah tuntas tidak lagi dimuat dashboard, tetapi
     * yang belum tuntas tetap tampil berapa pun umurnya.
     */
    public function test_dashboard_membatasi_arsip_tetapi_tidak_menyembunyikan_yang_belum_tuntas(): void
    {
        $pengguna = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        $lamaSelesai = $this->perjalanan($pengguna, 1);
        $lamaSelesai->update([
            'status' => StatusUsulan::Selesai->value,
            'tanggal_mulai' => today()->subYears(2)->toDateString(),
            'tanggal_selesai' => today()->subYears(2)->addDays(2)->toDateString(),
        ]);

        $lamaBelumTuntas = $this->perjalanan($pengguna, 2);
        $lamaBelumTuntas->update([
            'tanggal_mulai' => today()->subYears(2)->toDateString(),
            'tanggal_selesai' => today()->subYears(2)->addDays(2)->toDateString(),
        ]);

        $pembayaran = $this->actingAs($pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('pembayaran');

        $this->assertSame(1, $pembayaran['total'], 'Hanya perjalanan yang belum tuntas yang tersisa.');
    }
}
