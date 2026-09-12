<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tim keuangan menagih berkas pertanggungjawaban yang belum diunggah
 * lewat tautan wa.me pada menu Keuangan.
 */
class PenagihanBerkasTest extends TestCase
{
    use RefreshDatabase;

    private PenagihDokumen $penagih;

    protected function setUp(): void
    {
        parent::setUp();

        $this->penagih = app(PenagihDokumen::class);
    }

    private function usulanSelesai(array $atribut = []): Usulan
    {
        return Usulan::factory()->create([
            'status' => StatusUsulan::Selesai->value,
            ...$atribut,
        ]);
    }

    /**
     * @param  array<string, mixed>  $berkas
     */
    private function lampirkan(Usulan $usulan, array $berkas): Usulan
    {
        Dokumen::factory()->create([
            'id_usulan' => $usulan->id,
            ...array_fill_keys(Usulan::DOKUMEN_LPJ_WAJIB, null),
            ...$berkas,
        ]);

        return $usulan->fresh('dokumen');
    }

    // ── Deteksi berkas kurang ──

    public function test_usulan_tanpa_dokumen_dianggap_kurang_seluruh_berkas(): void
    {
        $usulan = $this->usulanSelesai();

        $kurang = $this->penagih->berkasKurang($usulan);

        // Berkas unggahan, kedua tiket, dan laporannya. Nota transportasi
        // lokal tidak ditagih selama tidak ada nominal yang dinyatakan.
        $this->assertCount(count(Usulan::DOKUMEN_LPJ_WAJIB) + 3, $kurang);
        $this->assertFalse($this->penagih->lengkap($usulan));
    }

    public function test_berkas_yang_sudah_diunggah_tidak_ikut_ditagih(): void
    {
        $usulan = $this->lampirkan($this->usulanSelesai(), [
            'sppd' => 'dokumen/sppd/a.pdf',
        ]);

        $usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 120_000, 'bukti' => 'dokumen/nota/1.pdf']);
        $usulan = $usulan->fresh(['dokumen', 'notaTransport']);

        $kurang = $this->penagih->berkasKurang($usulan);

        $this->assertNotContains('SPPD bertanda tangan', $kurang);
        $this->assertNotContains('Nota/biaya transportasi lokal', $kurang);
        // Tiket disebut beserta bagian yang kurang, supaya pelaksana tahu apa yang ditagih.
        $this->assertContains('Tiket Pergi (data tiket)', $kurang);
        $this->assertContains('Laporan perjalanan dinas', $kurang);
    }

    public function test_ruas_bernominal_tanpa_nota_ikut_ditagih(): void
    {
        $usulan = $this->lampirkan($this->usulanSelesai(), ['sppd' => 'dokumen/sppd/a.pdf']);

        $usulan->notaTransport()->create(['urutan' => 1, 'nominal' => 120_000, 'bukti' => 'dokumen/nota/1.pdf']);
        $usulan->notaTransport()->create(['urutan' => 3, 'nominal' => 80_000]);

        $kurang = $this->penagih->berkasKurang($usulan->fresh(['dokumen', 'notaTransport']));

        $this->assertContains('Nota transportasi lokal untuk ruas 3', $kurang);
    }

    public function test_berkas_lengkap_menutup_penagihan(): void
    {
        $usulan = $this->lengkapiPertanggungjawaban($this->usulanSelesai());

        $this->assertSame([], $this->penagih->berkasKurang($usulan));
        $this->assertTrue($this->penagih->lengkap($usulan));
        $this->assertNull($this->penagih->tautanWhatsapp($usulan));
        $this->assertSame('Berkas sudah lengkap', $this->penagih->alasanTidakTersedia($usulan));
    }

    // ── Tautan wa.me ──

    public function test_nomor_lokal_diubah_ke_format_internasional(): void
    {
        $usulan = $this->usulanSelesai([
            'id_user' => User::factory()->create(['no_hp' => '0812-3456-7890'])->id,
        ]);

        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $this->penagih->tautanWhatsapp($usulan));
    }

    public function test_tautan_memuat_nomor_usulan_dan_daftar_berkas_kurang(): void
    {
        $usulan = $this->usulanSelesai([
            'id_user' => User::factory()->create(['nama' => 'Sri Handayani', 'no_hp' => '081200001111'])->id,
        ]);
        $usulan = $this->lampirkan($usulan, ['sppd' => 'dokumen/sppd/a.pdf']);

        $pesan = urldecode($this->penagih->tautanWhatsapp($usulan));

        $this->assertStringContainsString('Sri Handayani', $pesan);
        $this->assertStringContainsString($usulan->no_usulan, $pesan);
        $this->assertStringContainsString('Tiket Pergi', $pesan);
        $this->assertStringNotContainsString('SPPD', $pesan);
    }

    public function test_tanpa_nomor_whatsapp_tautan_tidak_tersedia(): void
    {
        $usulan = $this->usulanSelesai([
            'id_user' => User::factory()->create(['no_hp' => null])->id,
        ]);

        $this->assertNull($this->penagih->tautanWhatsapp($usulan));
        $this->assertSame('Nomor WhatsApp pelaksana belum terdaftar', $this->penagih->alasanTidakTersedia($usulan));
    }

    // ── Tampilan pada menu keuangan ──

    public function test_tombol_tagih_muncul_di_menu_keuangan(): void
    {
        $usulan = $this->usulanSelesai([
            'id_user' => User::factory()->create(['no_hp' => '081200002222'])->id,
        ]);
        $this->lampirkan($usulan, ['sppd' => 'dokumen/sppd/a.pdf']);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]))
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee('https://wa.me/6281200002222', false)
            ->assertSee('Tagih 5 berkas');
    }

    public function test_baris_dengan_berkas_lengkap_menampilkan_keterangan_bukan_tombol(): void
    {
        $usulan = $this->usulanSelesai([
            'id_user' => User::factory()->create(['no_hp' => '081200003333'])->id,
        ]);
        $this->lengkapiPertanggungjawaban($usulan);

        $this->actingAs(User::factory()->create(['role' => PeranPengguna::Bendahara->value]))
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee('Berkas sudah lengkap')
            ->assertDontSee('https://wa.me/6281200003333', false);
    }
}
