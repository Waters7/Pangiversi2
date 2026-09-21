<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PenagihDokumen;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transport lokal diperiksa tim keuangan sebelum berjalan ke pelaksana lalu
 * ke PPK — sama seperti baris rincian biaya, sebab nominalnya juga datang
 * dari nota yang diunggah pelaksana.
 */
class ValidasiTransportLokalTest extends TestCase
{
    use RefreshDatabase;

    private User $timKeuangan;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);

        $this->usulan = Usulan::factory()->create(['status' => StatusUsulan::Disetujui->value]);
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->usulan->id_user,
            'nama' => $this->usulan->user?->nama ?? 'Pelaksana',
            'nip' => $this->usulan->user?->nip,
            'peran' => 'ketua',
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
    }

    private function daftar(): DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $this->usulan->id);
    }

    private function validasiRincianBiaya(): void
    {
        $this->usulan->fresh('keuangan')->keuangan->rincianBiaya()
            ->whereNull('divalidasi_at')
            ->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);
    }

    // ── Validasi ──

    public function test_tim_keuangan_memvalidasi_transport_lokal(): void
    {
        $this->assertNull($this->daftar()->divalidasi_at);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.validasi', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $daftar = $this->daftar();

        $this->assertNotNull($daftar->divalidasi_at);
        $this->assertSame($this->timKeuangan->id, $daftar->id_validator);
    }

    public function test_validasi_dapat_dicabut(): void
    {
        $this->daftar()->update(['divalidasi_at' => now(), 'id_validator' => $this->timKeuangan->id]);

        $this->actingAs($this->timKeuangan)
            ->delete(route('daftar-riil.batal-validasi', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNull($this->daftar()->divalidasi_at);
    }

    public function test_ppk_tidak_memvalidasi_transport_lokal(): void
    {
        $this->actingAs(User::factory()->ppk()->create())
            ->put(route('daftar-riil.validasi', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    // ── Menahan pengiriman ──

    /**
     * Rincian biaya boleh sudah bersih, tapi selama transport lokalnya
     * belum diperiksa berkasnya tetap tertahan.
     */
    public function test_berkas_tertahan_selama_transport_lokal_belum_divalidasi(): void
    {
        $this->validasiRincianBiaya();

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('error', fn (string $pesan) => str_contains($pesan, 'Transport lokal belum divalidasi'));

        $this->assertNull($this->daftar()->dikirim_ke_pegawai_at);
    }

    /**
     * Alasan tertahannya tampak di samping tombol kirim, bukan baru muncul
     * sebagai pesan penolakan setelah tombolnya ditekan.
     */
    public function test_halaman_keuangan_menyebut_transport_lokal_yang_menahan_pengiriman(): void
    {
        $this->validasiRincianBiaya();

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Transport lokal belum divalidasi');
    }

    public function test_mencabut_validasi_transport_tercatat_di_jejak_audit(): void
    {
        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.validasi', [$this->usulan, $this->peserta]));
        $this->actingAs($this->timKeuangan)
            ->delete(route('daftar-riil.batal-validasi', [$this->usulan, $this->peserta]));

        $this->assertDatabaseHas('audit_logs', [
            'id_usulan' => $this->usulan->id,
            'deskripsi' => "Validasi transport lokal pada usulan {$this->usulan->no_usulan} dicabut oleh {$this->timKeuangan->nama}.",
        ]);
    }

    public function test_berkas_berjalan_setelah_keduanya_divalidasi(): void
    {
        $this->validasiRincianBiaya();

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.validasi', [$this->usulan, $this->peserta]));

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->assertNotNull($this->daftar()->dikirim_ke_pegawai_at);
    }

    /**
     * Perjalanan tanpa transport lokal tidak perlu divalidasi: tidak ada
     * yang diganti, dan menahannya hanya memacetkan berkas.
     */
    public function test_tanpa_transport_lokal_berkas_tetap_berjalan(): void
    {
        $this->usulan->notaTransport()->update(['nominal' => null]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
        $this->validasiRincianBiaya();

        $this->assertSame(0.0, $this->daftar()->total_riil);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');
    }

    // ── Nota terlihat ──

    public function test_menu_transport_lokal_menautkan_nota_pelaksana(): void
    {
        $this->usulan->notaTransport()->where('urutan', 1)->update(['bukti' => 'dokumen/nota-ruas-1.pdf']);

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.transport-lokal'))
            ->assertOk()
            ->assertSee('Nota')
            ->assertSee(route('berkas.lihat', 'dokumen/nota-ruas-1.pdf'))
            ->assertSee('target="_blank" rel="noopener"', false);
    }

    public function test_tombol_validasi_tampil_pada_menu_transport_lokal(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.transport-lokal'))
            ->assertOk()
            ->assertSee(route('daftar-riil.validasi', [$this->usulan, $this->peserta]))
            // Ditanya ulang sekali sebelum tercatat, seperti validasi rincian biaya.
            ->assertSee('Validasi komponen ini?')
            ->assertSee('Ya, Validasi');
    }

    public function test_mencabut_validasi_transport_ditanya_ulang(): void
    {
        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.validasi', [$this->usulan, $this->peserta]));

        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.transport-lokal'))
            ->assertOk()
            ->assertSee(route('daftar-riil.batal-validasi', [$this->usulan, $this->peserta]))
            ->assertSee('Cabut validasi komponen ini?')
            ->assertSee('Ya, Cabut');
    }

    // ── Checklist LPJ ──

    /**
     * Checklist mengikuti aturan kelengkapan yang sama dengan penagihan,
     * bukan daftar berkas warisan.
     */
    public function test_checklist_menyebut_berkas_yang_benar_benar_diunggah(): void
    {
        $checklist = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()));

        $label = $checklist->pluck('label');

        $this->assertTrue($label->contains('Tiket Pergi'));
        $this->assertTrue($label->contains('Tiket Pulang'));
        $this->assertTrue($label->contains('Nota Transportasi Lokal'));
        $this->assertTrue($label->contains('Laporan Perjalanan Dinas'));

        // Berkas warisan yang tidak lagi diunggah pelaksana tidak disebut.
        $this->assertFalse($label->contains('Boarding Pass'));
        $this->assertFalse($label->contains('Laporan Hasil'));
    }

    public function test_checklist_menandai_tiket_yang_belum_lengkap(): void
    {
        $this->usulan->tiket()->where('arah', 'pulang')->update(['kode_booking' => null]);

        $checklist = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()));

        $this->assertFalse($checklist->firstWhere('label', 'Tiket Pulang')['terpenuhi']);
        $this->assertTrue($checklist->firstWhere('label', 'Tiket Pergi')['terpenuhi']);
    }

    public function test_checklist_menuntut_nomor_transaksi_bill_hotel(): void
    {
        $this->usulan->dokumen()->update(['bill_hotel_no_transaksi' => null]);

        $checklist = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()));

        $this->assertFalse($checklist->firstWhere('label', 'Bill Hotel')['terpenuhi']);
    }

    /**
     * Laporan perjadin diisi di aplikasi, jadi tidak punya berkas untuk
     * dibuka — checklistnya tetap menyebutnya sebagai kewajiban.
     */
    public function test_laporan_perjadin_tidak_punya_berkas_untuk_dilihat(): void
    {
        $baris = collect(app(PenagihDokumen::class)->checklist($this->usulan->fresh()))
            ->firstWhere('label', 'Laporan Perjalanan Dinas');

        $this->assertTrue($baris['terpenuhi']);
        $this->assertSame([], $baris['berkas']);
    }
}
