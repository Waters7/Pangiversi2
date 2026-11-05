<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Halaman muka tiap pengguna memberi tahu dokumen pada Rincian Saya yang
 * sudah dikirim tim keuangan dan menunggu tanda tangannya.
 */
class DokumenTandaTanganDasborTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => 'Jakarta',
        ]);

        $keuangan = Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
        $keuangan->rincianBiaya()->create([
            'kategori' => KategoriBiaya::UangHarian->value,
            'komponen' => 'Uang harian',
            'volume' => 2,
            'satuan' => 'OH',
            'harga_satuan' => 350_000,
            'jumlah' => 700_000,
        ]);
    }

    /** Berkas pelaksana, sudah dikirim tim keuangan bila $dikirim. */
    private function berkas(int $totalRiil = 0, bool $dikirim = true, ?User $pemilik = null): DaftarRiil
    {
        $pemilik ??= $this->pelaksana;

        /** @var PesertaUsulan $peserta */
        $peserta = $this->usulan->peserta()->create(['id_user' => $pemilik->id, 'nama' => $pemilik->nama, 'peran' => 'ketua']);

        $berkas = DaftarRiil::create(['id_usulan' => $this->usulan->id, 'id_peserta' => $peserta->id, 'total_riil' => $totalRiil]);

        if ($dikirim) {
            $berkas->kirimKePegawai();
        }

        return $berkas->fresh();
    }

    private function dasbor(?User $pengguna = null): TestResponse
    {
        return $this->actingAs($pengguna ?? $this->pelaksana)->get(route('dashboard'))->assertOk();
    }

    public function test_rincian_dan_daftar_riil_terkirim_tampil_dengan_tautan_ke_rincian_saya(): void
    {
        $this->berkas(totalRiil: 150_000);

        $this->dasbor()
            ->assertSee('data-tanda-tangan-saya', false)
            ->assertSeeInOrder(['Rincian Biaya Perjalanan Dinas', $this->usulan->no_usulan, 'Jakarta', 'Rp 700.000'])
            ->assertSeeInOrder(['Daftar Pengeluaran Riil', 'Rp 150.000'])
            ->assertSee(route('rincian-saya.rincian-biaya', ['cari' => $this->usulan->no_usulan]), false)
            ->assertSee(route('rincian-saya.daftar-riil', ['cari' => $this->usulan->no_usulan]), false);
    }

    /** Perjalanan tanpa transport lokal tidak punya daftar riil untuk ditandatangani. */
    public function test_daftar_riil_tanpa_nominal_tidak_ditagih(): void
    {
        $this->berkas();

        $this->dasbor()
            ->assertSee('Rincian Biaya Perjalanan Dinas')
            ->assertDontSee('Daftar Pengeluaran Riil');
    }

    public function test_berkas_yang_belum_dikirim_tim_keuangan_tidak_tampil(): void
    {
        $this->berkas(dikirim: false);

        $this->dasbor()->assertDontSee('data-tanda-tangan-saya', false);
    }

    public function test_dokumen_yang_sudah_disikapi_hilang_dari_daftar(): void
    {
        $berkas = $this->berkas(totalRiil: 150_000);

        $berkas->jalurRincian()->setujui();
        $berkas->jalur()->sanggah('Nominal nota tidak sesuai.');

        $this->dasbor()->assertDontSee('data-tanda-tangan-saya', false);
    }

    public function test_masa_sanggah_lewat_tetap_ditagih_tanpa_batas_sanggah(): void
    {
        $this->berkas()->update(['batas_sanggah' => today()->subDay()]);

        $this->dasbor()
            ->assertSee('Rincian Biaya Perjalanan Dinas')
            ->assertDontSee('sanggah sampai');
    }

    public function test_hanya_pemilik_berkas_yang_melihatnya_apa_pun_perannya(): void
    {
        $bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);

        $this->berkas();
        $this->dasbor($bendahara)->assertDontSee('data-tanda-tangan-saya', false);

        $this->berkas(pemilik: $bendahara);
        $this->dasbor($bendahara)->assertSee('data-tanda-tangan-saya', false);
    }
}
