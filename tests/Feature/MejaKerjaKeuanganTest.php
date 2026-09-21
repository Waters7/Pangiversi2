<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman keuangan adalah tempat tim keuangan memeriksa nominal sebelum
 * berkas berjalan ke pelaksana. Dua hal harus terbaca sekilas di sana:
 * mana nominal yang belum divalidasi, dan seperti apa bukti yang diunggah.
 */
class MejaKerjaKeuanganTest extends TestCase
{
    use RefreshDatabase;

    private User $timKeuangan;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->usulan->peserta()->create([
            'id_user' => $this->usulan->id_user,
            'nama' => $this->usulan->user?->nama ?? 'Pelaksana',
            'nip' => $this->usulan->user?->nip,
            'peran' => 'ketua',
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
    }

    private function buka()
    {
        return $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan->no_usulan))
            ->assertOk();
    }

    private function rincianDokumen(): RincianBiaya
    {
        return $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()
            ->where('sumber', RincianBiaya::SUMBER_DOKUMEN)
            ->firstOrFail();
    }

    // ── Aksi validasi ──

    public function test_kolom_validasi_tampil_pada_tabel_rincian(): void
    {
        $this->buka()
            ->assertSee('Validasi')
            ->assertSee('Nominal dari pelaksana');
    }

    public function test_tombol_validasi_tersedia_untuk_nominal_dari_pelaksana(): void
    {
        $baris = $this->rincianDokumen();

        $this->buka()->assertSee(
            route('keuangan.rincian.validasi', [$this->usulan->no_usulan, $baris->id])
        );
    }

    public function test_status_berubah_setelah_divalidasi(): void
    {
        $baris = $this->rincianDokumen();

        $this->buka()->assertSee('Belum diperiksa');

        $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.validasi', [$this->usulan->no_usulan, $baris->id]))
            ->assertSessionHas('success');

        $this->assertNotNull($baris->fresh()->divalidasi_at);

        $this->buka()->assertSee('Sudah divalidasi');
    }

    /**
     * Aksinya tinggal satu tombol ikon; keadaannya dibaca dari kolom Status
     * di sebelahnya, bukan dari tulisan pada tombol.
     */
    public function test_pencabutan_validasi_tersedia_sebagai_tombol_ikon(): void
    {
        $baris = $this->rincianDokumen();
        $baris->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        $this->buka()
            ->assertSee(route('keuangan.rincian.batal-validasi', [$this->usulan->no_usulan, $baris->id]))
            ->assertSee('Cabut validasi nominal ini');
    }

    /**
     * Baris yang ditulis tim keuangan sendiri tidak perlu divalidasi —
     * merekalah penulisnya.
     */
    public function test_baris_tulisan_tim_keuangan_tidak_menawarkan_validasi(): void
    {
        $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()->create([
            'kategori' => KategoriBiaya::UangHarian->value,
            'komponen' => 'Uang harian perjalanan dinas',
            'volume' => 3,
            'satuan' => 'hari',
            'harga_satuan' => 530_000,
            'jumlah' => 1_590_000,
            'sumber' => RincianBiaya::SUMBER_KEUANGAN,
        ]);

        $this->buka()->assertSee('Ditulis tim keuangan');
    }

    // ── Lihat dokumen LPJ ──

    public function test_checklist_menautkan_dokumen_yang_sudah_diunggah(): void
    {
        $dokumen = Dokumen::firstWhere('id_usulan', $this->usulan->id);

        $this->buka()
            ->assertSee('Lihat')
            ->assertSee(route('berkas.lihat', $dokumen->surat_tugas));
    }

    /**
     * Tautannya membuka tab baru: halaman keuangan yang sedang dikerjakan
     * tidak boleh ikut berpindah.
     */
    public function test_tautan_dokumen_dibuka_di_tab_baru(): void
    {
        $this->buka()->assertSee('target="_blank" rel="noopener"', false);
    }

    public function test_berkas_yang_belum_diunggah_tidak_bertombol_lihat(): void
    {
        Dokumen::where('id_usulan', $this->usulan->id)->update(['bill_hotel' => null]);

        $isi = $this->buka()->getContent();

        // Baris Bill Hotel tetap ada, tapi tanpa tautannya.
        $this->assertStringContainsString('Bill Hotel', $isi);
        $this->assertStringContainsString('Belum lengkap', $isi);
    }
}
