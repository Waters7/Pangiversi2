<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\AntreanPeran;
use App\Services\JalurPersetujuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lencana angka pada sidebar: berapa berkas menunggu tindakan seseorang.
 *
 * Angkanya dihitung lewat SQL supaya sidebar tidak memuat seluruh berkas
 * tiap halaman. Karena itu yang paling penting diuji di sini bukan
 * angkanya sendiri, melainkan bahwa cerminan SQL-nya sepakat dengan
 * keputusan PHP yang dipakai halaman aslinya.
 */
class AntreanPeranTest extends TestCase
{
    use RefreshDatabase;

    private function antrean(): AntreanPeran
    {
        return app(AntreanPeran::class);
    }

    private function usulan(): Usulan
    {
        return Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
    }

    /**
     * Berkas yang sudah dikirim ke pelaksana dan disetujuinya — keadaan
     * paling lazim dari "menunggu tanda tangan PPK".
     */
    private function berkasSiapTandaTangan(): DaftarRiil
    {
        return DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan()->id,
            'total_riil' => 250_000,
            'dikirim_ke_pegawai_at' => now(),
            'batas_sanggah' => today()->addDays(3),
            'disetujui_pegawai_at' => now(),
        ]);
    }

    private function rincian(Keuangan $keuangan, KategoriBiaya $kategori, float $jumlah): RincianBiaya
    {
        return RincianBiaya::create([
            'id_keuangan' => $keuangan->id,
            'kategori' => $kategori->value,
            'komponen' => $kategori->label(),
            'volume' => 1,
            'satuan' => 'hari',
            'harga_satuan' => $jumlah,
            'jumlah' => $jumlah,
        ]);
    }

    // ── Kesepakatan SQL dengan PHP ──

    public function test_saringan_sql_sepakat_dengan_keputusan_php(): void
    {
        // Satu berkas untuk tiap keadaan yang mungkin, supaya kedua cara
        // menilai benar-benar diadu.
        $usulan = fn () => $this->usulan()->id;

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan(), 'total_riil' => 100_000,
        ]);

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan(), 'total_riil' => 100_000,
            'dikirim_ke_pegawai_at' => now(), 'batas_sanggah' => today()->addDays(3),
        ]);

        $this->berkasSiapTandaTangan();

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan(), 'total_riil' => 100_000,
            'dikirim_ke_pegawai_at' => now(), 'batas_sanggah' => today()->subDay(),
        ]);

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan(), 'total_riil' => 100_000,
            'dikirim_ke_pegawai_at' => now(), 'batas_sanggah' => today()->addDays(3),
            'disanggah_at' => now(),
        ]);

        DaftarRiil::factory()->create([
            'id_usulan' => $usulan(), 'total_riil' => 0,
            'dikirim_ke_pegawai_at' => now(), 'batas_sanggah' => today()->addDays(3),
            'disetujui_pegawai_at' => now(),
        ]);

        DaftarRiil::factory()->ditandatangani()->create([
            'id_usulan' => $usulan(), 'total_riil' => 100_000,
            'dikirim_ke_pegawai_at' => now(), 'batas_sanggah' => today()->addDays(3),
            'disetujui_pegawai_at' => now(),
        ]);

        $lewatSql = DaftarRiil::menungguPpk(JalurPersetujuan::RIIL)
            ->pluck('id')
            ->sort()
            ->values();

        $lewatPhp = DaftarRiil::all()
            ->filter(fn (DaftarRiil $d) => ! $d->sudah_ditandatangani && $d->siapDitandatanganiPpk())
            ->pluck('id')
            ->sort()
            ->values();

        $this->assertSame(
            $lewatPhp->all(),
            $lewatSql->all(),
            'Saringan SQL dan keputusan PHP menghasilkan berkas yang berbeda.',
        );

        // Kedua keadaan yang sah memang tertangkap, bukan sekadar kebetulan
        // sama-sama kosong.
        $this->assertCount(2, $lewatSql);
    }

    // ── Tiap antrean ──

    public function test_menghitung_daftar_riil_yang_menunggu_ppk(): void
    {
        $this->berkasSiapTandaTangan();
        DaftarRiil::factory()->create(['id_usulan' => $this->usulan()->id, 'total_riil' => 100_000]);

        $this->assertSame(1, $this->antrean()->riilMenungguPpk());
    }

    public function test_menghitung_rincian_biaya_yang_menunggu_ppk(): void
    {
        $berkas = DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan()->id,
            'total_riil' => 250_000,
            'dikirim_ke_pegawai_at' => now(),
            'batas_sanggah' => today()->addDays(3),
            'rincian_disetujui_at' => now(),
        ]);

        // Tanpa baris rincian, jalur rincian belum punya nilai apa pun.
        $this->assertSame(0, $this->antrean()->rincianMenungguPpk());

        $keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $berkas->id_usulan]);

        $this->rincian($keuangan, KategoriBiaya::UangHarian, 400_000);

        $this->assertSame(1, $this->antrean()->rincianMenungguPpk());
    }

    /** Transport lokal dipertanggungjawabkan di daftar riil, bukan di sini. */
    public function test_rincian_yang_hanya_berisi_transport_lokal_tidak_dihitung(): void
    {
        $berkas = DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan()->id,
            'total_riil' => 250_000,
            'dikirim_ke_pegawai_at' => now(),
            'batas_sanggah' => today()->addDays(3),
            'rincian_disetujui_at' => now(),
        ]);

        $keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $berkas->id_usulan]);

        $this->rincian($keuangan, KategoriBiaya::TransportLokal, 75_000);

        $this->assertSame(0, $this->antrean()->rincianMenungguPpk());
    }

    public function test_menghitung_nominatif_yang_belum_ditandatangani(): void
    {
        DaftarNominatif::create(['no_tugas' => 'KP.03.01/F.XXXVIII/1/2026']);
        DaftarNominatif::create([
            'no_tugas' => 'KP.03.01/F.XXXVIII/2/2026',
            'ditandatangani_at' => now(),
        ]);

        $this->assertSame(1, $this->antrean()->nominatifMenungguPpk());
    }

    public function test_menghitung_perjadin_yang_uang_mukanya_belum_ditransfer(): void
    {
        $belum = $this->usulan();
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $belum->id]);

        $sudah = $this->usulan();
        Keuangan::factory()->belumBayar()->create([
            'id_usulan' => $sudah->id,
            'tanggal_transfer' => today(),
        ]);

        $this->assertSame(1, $this->antrean()->menungguDibayar());
    }

    public function test_menghitung_berkas_yang_komponennya_belum_divalidasi(): void
    {
        $keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan()->id]);

        $this->rincian($keuangan, KategoriBiaya::UangHarian, 400_000);

        $this->assertSame(1, $this->antrean()->menungguValidasi());

        $keuangan->rincianBiaya()->update(['divalidasi_at' => now()]);

        $this->assertSame(0, $this->antrean()->menungguValidasi());
    }

    // ── Kewenangan ──

    public function test_hanya_menghitung_antrean_yang_menjadi_wewenang_penggunanya(): void
    {
        $this->berkasSiapTandaTangan();

        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);
        $bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);
        $pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        $this->assertArrayHasKey('verifikasi-riil', $this->antrean()->untuk($ppk));

        // Bendahara tidak menandatangani dokumen pertanggungjawaban, jadi
        // antrean itu bukan urusannya.
        $this->assertArrayNotHasKey('verifikasi-riil', $this->antrean()->untuk($bendahara));

        $this->assertSame([], $this->antrean()->untuk($pelaksana));
    }

    /** Menu tanpa antrean tidak memunculkan angka nol di sidebar. */
    public function test_antrean_kosong_tidak_ikut_ditampilkan(): void
    {
        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);

        $this->assertSame([], $this->antrean()->untuk($ppk));
    }

    public function test_lencana_muncul_di_sidebar(): void
    {
        $this->berkasSiapTandaTangan();

        $ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);

        $this->actingAs($ppk)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('berkas menunggu tindakan Anda');
    }
}
