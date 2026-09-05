<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rincian biaya (Lampiran II) dan daftar pengeluaran riil (Lampiran IX)
 * adalah dua dokumen dengan isi yang tidak boleh bertumpang tindih:
 * rincian memuat seluruh komponen kecuali transport lokal, daftar riil
 * hanya memuat transport lokal.
 */
class FormatDokumenCetakTest extends TestCase
{
    use RefreshDatabase;

    private const NO_TUGAS = 'KP.03.01/F.XXXVIII/64/2026';

    private User $timKeuangan;

    private User $ppk;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
        $this->ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'no_tugas' => self::NO_TUGAS,
            'status' => StatusUsulan::Disetujui->value,
        ]);

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

    // ── Nama komponen ──

    public function test_nama_tiket_membawa_kode_booking(): void
    {
        $komponen = $this->rincian()
            ->filter(fn (RincianBiaya $b) => $b->kategori === KategoriBiaya::Transport)
            ->pluck('komponen');

        $this->assertTrue($komponen->every(fn (string $n) => str_contains($n, 'Kode Booking')));
        $this->assertTrue($komponen->contains(fn (string $n) => str_starts_with($n, 'Tiket Pulang')));
    }

    public function test_nama_hotel_membawa_nomor_transaksi(): void
    {
        $hotel = $this->rincian()
            ->first(fn (RincianBiaya $b) => $b->kategori === KategoriBiaya::Penginapan);

        $this->assertNotNull($hotel);
        $this->assertStringStartsWith('Biaya Hotel', $hotel->komponen);
        $this->assertStringContainsString('No. Transaksi', $hotel->komponen);
    }

    // ── Isi tiap dokumen ──

    /**
     * Transport lokal tidak boleh ikut ke rincian biaya, sebab ia sudah
     * dipertanggungjawabkan pada daftar riil — kalau ikut, nominalnya
     * terhitung dua kali.
     */
    public function test_rincian_biaya_memuat_semua_kecuali_transport_lokal(): void
    {
        $kategori = $this->rincian()->pluck('kategori')->map->value->unique()->sort()->values();

        $this->assertTrue($kategori->contains('transport'));
        $this->assertTrue($kategori->contains('penginapan'));
        $this->assertFalse($kategori->contains('transport_lokal'));
    }

    public function test_daftar_riil_hanya_memuat_transport_lokal(): void
    {
        $daftar = $this->daftar();

        $this->assertNotEmpty($daftar->rincian);
        $this->assertSame(
            (float) $daftar->rincian->sum('nominal'),
            (float) $daftar->total_riil,
        );
    }

    public function test_cetakan_kedua_dokumen_terbentuk(): void
    {
        $this->actingAs($this->timKeuangan)
            ->get(route('keuangan.cetak-rincian', $this->usulan->no_usulan))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->timKeuangan)
            ->get(route('daftar-riil.cetak', [$this->usulan, $this->peserta]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // ── QR tanda tangan PPK pada daftar nominatif ──

    public function test_kode_verifikasi_terbit_saat_ppk_menandatangani(): void
    {
        $nominatif = $this->nominatif();

        $this->assertNull($nominatif->kode_verifikasi);

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.nominatif.tanda-tangan', $nominatif))
            ->assertSessionHas('success');

        $nominatif->refresh();

        $this->assertNotNull($nominatif->kode_verifikasi);
        $this->assertStringStartsWith('NOM-', $nominatif->kode_verifikasi);
    }

    /**
     * Kodenya menetap agar QR pada cetakan lama tetap dapat diperiksa.
     */
    public function test_kode_verifikasi_tidak_berubah_saat_dicetak_ulang(): void
    {
        $nominatif = $this->nominatif();
        $nominatif->update(['ditandatangani_at' => now(), 'id_ppk' => $this->ppk->id]);
        $nominatif->terbitkanKodeVerifikasi();

        $kode = $nominatif->fresh()->kode_verifikasi;

        $nominatif->fresh()->terbitkanKodeVerifikasi();

        $this->assertSame($kode, $nominatif->fresh()->kode_verifikasi);
    }

    public function test_halaman_verifikasi_menyebut_surat_tugas_dan_penandatangannya(): void
    {
        $nominatif = $this->nominatif();
        $nominatif->update(['ditandatangani_at' => now(), 'id_ppk' => $this->ppk->id]);
        $nominatif->terbitkanKodeVerifikasi();

        // Terbuka tanpa login: pemeriksa dokumen fisik tidak punya akun.
        $this->get(route('verifikasi.tampil', $nominatif->fresh()->kode_verifikasi))
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi')
            ->assertSee(self::NO_TUGAS)
            ->assertSee($this->usulan->no_usulan)
            ->assertSee($this->ppk->nama)
            ->assertSee('Ditandatangani PPK Pada');
    }

    public function test_kode_yang_belum_ditandatangani_tidak_terverifikasi(): void
    {
        $nominatif = $this->nominatif();
        $nominatif->update(['kode_verifikasi' => 'NOM-BELUMSAH12']);

        $this->get(route('verifikasi.tampil', 'NOM-BELUMSAH12'))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terverifikasi');
    }

    // ── Pembantu ──

    private function rincian()
    {
        return $this->usulan->fresh('keuangan')->keuangan->rincianBiaya;
    }

    private function daftar(): DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $this->usulan->id);
    }

    private function nominatif(): DaftarNominatif
    {
        return DaftarNominatif::firstOrCreate(['no_tugas' => self::NO_TUGAS]);
    }
}
