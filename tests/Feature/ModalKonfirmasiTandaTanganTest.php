<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tanda tangan tidak dapat ditarik kembali, jadi setiap tombolnya melewati
 * kotak konfirmasi yang menjelaskan akibatnya lebih dulu.
 */
class ModalKonfirmasiTandaTanganTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private User $pelaksana;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create([
            'role' => PeranPengguna::DosenTendik->value,
            'nama' => 'Rahmatullah Pontoh',
        ]);
        $this->ppk = User::factory()->create(['role' => PeranPengguna::Ppk->value]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->peserta = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
        ]);
    }

    private function daftar(float $nominal = 1_450_000): DaftarRiil
    {
        return DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => $nominal,
        ]);
    }

    // ── Konfirmasi pelaksana ──

    public function test_tombol_setuju_membuka_kotak_konfirmasi(): void
    {
        $daftar = $this->daftar();
        $daftar->kirimKePegawai();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee("\$dispatch('buka-riil-setuju-{$daftar->id}')", false)
            ->assertSee('x-on:buka-riil-setuju-'.$daftar->id.'.window', false);
    }

    public function test_kotak_konfirmasi_pelaksana_menyebut_nominal_dan_akibatnya(): void
    {
        $this->daftar()->kirimKePegawai();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            // Tiap dokumen ditandatangani sendiri, jadi kotaknya menyebut namanya.
            ->assertSee('Tandatangani Daftar Pengeluaran Riil ini?')
            ->assertSee('Apakah Anda yakin ini sudah benar dikerjakan?')
            ->assertSee('Rp 1.450.000')
            ->assertSee($this->usulan->no_usulan)
            ->assertSee('tidak dapat disanggah lagi')
            ->assertSee('Ya, Tandatangani');
    }

    public function test_formnya_tetap_menuju_rute_persetujuan(): void
    {
        $this->daftar()->kirimKePegawai();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee(route('daftar-riil.setuju', [$this->usulan, $this->peserta, 'riil']), false);
    }

    public function test_kotak_tidak_dirender_saat_masa_sanggah_berakhir(): void
    {
        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->update(['batas_sanggah' => today()->subDay()]);

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertDontSee('Tandatangani rincian ini?');
    }

    // ── Konfirmasi PPK ──

    private function siapDitandatangani(): DaftarRiil
    {
        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->setujuiPegawai();

        return $daftar->fresh();
    }

    public function test_tombol_ppk_membuka_kotak_konfirmasi(): void
    {
        $this->siapDitandatangani();

        $this->actingAs($this->ppk)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee("\$dispatch('buka-ttd-ppk-{$this->peserta->id}')", false)
            ->assertSee('Tandatangani sebagai PPK?');
    }

    public function test_kotak_ppk_menjelaskan_penerbitan_kode_verifikasi(): void
    {
        $this->siapDitandatangani();

        $this->actingAs($this->ppk)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee('Rahmatullah Pontoh')
            ->assertSee('Rp 1.450.000')
            ->assertSee('menerbitkan kode verifikasi')
            ->assertSee(route('daftar-riil.tanda-tangan', [$this->usulan->no_usulan, $this->peserta]), false);
    }

    public function test_kotak_ppk_menyebut_masa_sanggah_yang_kedaluwarsa(): void
    {
        $daftar = $this->daftar();
        $daftar->kirimKePegawai();
        $daftar->update(['batas_sanggah' => today()->subDay()]);

        $this->actingAs($this->ppk)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee('Pelaksana tidak menanggapi sampai masa sanggah berakhir');
    }

    public function test_tombol_ppk_tidak_muncul_sebelum_pelaksana_menanggapi(): void
    {
        $this->daftar()->kirimKePegawai();

        $this->actingAs($this->ppk)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertDontSee('Tandatangani sebagai PPK?');
    }

    // ── Pembatalan tanda tangan ──

    public function test_pembatalan_tanda_tangan_juga_dikonfirmasi(): void
    {
        $daftar = $this->siapDitandatangani();
        $daftar->tandaTangani($this->ppk);

        $this->actingAs($this->ppk)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee('Batalkan tanda tangan?')
            ->assertSee($daftar->fresh()->kode_verifikasi)
            ->assertSee('tidak akan lagi tervalidasi')
            ->assertSee('Ya, Batalkan');
    }

    public function test_dialog_bawaan_peramban_tidak_dipakai_lagi(): void
    {
        $daftar = $this->siapDitandatangani();
        $daftar->tandaTangani($this->ppk);

        $isi = $this->actingAs($this->ppk)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('confirm(', $isi);
    }
}
