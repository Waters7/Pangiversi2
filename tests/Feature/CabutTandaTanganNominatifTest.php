<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\DaftarNominatif;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Daftar nominatif yang ternyata keliru dapat ditarik PPK: tanda tangannya
 * dicabut, QR lamanya gugur, lalu ditandatangani ulang setelah dikoreksi.
 */
class CabutTandaTanganNominatifTest extends TestCase
{
    use RefreshDatabase;

    private const ALASAN = 'Akun pembebanan keliru, seharusnya 524111.';

    private User $ppk;

    private User $timKeuangan;

    private DaftarNominatif $daftar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ppk = User::factory()->ppk()->create();
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);

        $usulan = Usulan::factory()->create([
            'no_tugas' => 'KP.03.01/F.XXXVIII/88/2026',
            'status' => StatusUsulan::Disetujui->value,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id]);

        $this->daftar = $this->terbitkanNominatif($this->lengkapiPertanggungjawaban($usulan), $this->ppk);
        $this->daftar->update(['id_ppk' => null, 'ditandatangani_at' => null, 'dikirim_at' => null]);

        $this->actingAs($this->ppk)->put(route('persetujuan.nominatif.tanda-tangan', $this->daftar));
        $this->daftar->refresh();
    }

    private function cabut(?User $pengguna = null, ?string $alasan = self::ALASAN): TestResponse
    {
        return $this->actingAs($pengguna ?? $this->ppk)
            ->delete(route('persetujuan.nominatif.batal-tanda-tangan', $this->daftar), ['alasan' => $alasan]);
    }

    public function test_tanda_tangan_dicabut_dan_daftar_ditarik_dari_tim_keuangan(): void
    {
        $this->cabut()->assertSessionHas('success');

        $this->daftar->refresh();

        $this->assertFalse($this->daftar->sudahDitandatangani());
        $this->assertFalse($this->daftar->sudahDikirim());
        $this->assertNull($this->daftar->id_ppk);
        $this->assertNull($this->daftar->kode_verifikasi);
        $this->assertSame('Menunggu Tanda Tangan PPK', $this->daftar->status_label);

        $this->assertDatabaseHas(AuditLog::class, [
            'aksi' => AuditLog::AKSI_DOKUMEN,
            'deskripsi' => "Tanda tangan daftar nominatif surat tugas {$this->daftar->no_tugas} dicabut: ".self::ALASAN,
        ]);
        $this->assertDatabaseHas('notifikasi', ['id_user' => $this->timKeuangan->id, 'judul' => 'Tanda tangan daftar nominatif dicabut']);
    }

    public function test_akun_dan_tim_kerja_tetap_setelah_dicabut(): void
    {
        $this->daftar->update(['id_akun_pembiayaan' => null, 'id_tim_kerja' => null]);
        $sebelum = $this->daftar->only('no_tugas', 'id_akun_pembiayaan', 'id_tim_kerja', 'id_kategori_pembiayaan');

        $this->cabut();

        $this->assertSame($sebelum, $this->daftar->fresh()->only(array_keys($sebelum)));
    }

    public function test_qr_lama_tidak_berlaku_dan_tanda_tangan_ulang_menerbitkan_kode_baru(): void
    {
        $kodeLama = $this->daftar->kode_verifikasi;

        $this->get(route('verifikasi.tampil', $kodeLama))->assertSee('Dokumen Terverifikasi');

        $this->cabut();

        $this->get(route('verifikasi.tampil', $kodeLama))->assertSee('Dokumen Tidak Terverifikasi');

        $this->actingAs($this->ppk)
            ->put(route('persetujuan.nominatif.tanda-tangan', $this->daftar))
            ->assertSessionHas('success');

        $kodeBaru = $this->daftar->fresh()->kode_verifikasi;

        $this->assertNotNull($kodeBaru);
        $this->assertNotSame($kodeLama, $kodeBaru);
    }

    public function test_pencabutan_wajib_menerangkan_kesalahannya(): void
    {
        $this->cabut(alasan: 'salah')->assertSessionHasErrors('alasan');
        $this->cabut(alasan: null)->assertSessionHasErrors('alasan');

        $this->assertTrue($this->daftar->fresh()->sudahDitandatangani());
    }

    public function test_daftar_yang_belum_ditandatangani_tidak_dapat_dicabut(): void
    {
        $this->daftar->batalkanTandaTangan();

        $this->cabut()->assertSessionHas('error', 'Daftar nominatif ini belum ditandatangani.');
    }

    public function test_hanya_penandatangan_yang_dapat_mencabut(): void
    {
        $this->cabut($this->timKeuangan)->assertForbidden();

        $this->assertTrue($this->daftar->fresh()->sudahDitandatangani());
    }

    public function test_tombol_cabut_hanya_ada_pada_daftar_bertanda_tangan(): void
    {
        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif.detail', $this->daftar))
            ->assertSee('data-cabut-nominatif', false);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif'))
            ->assertSee('data-cabut-nominatif', false);

        $this->cabut();

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.nominatif.detail', $this->daftar))
            ->assertDontSee('data-cabut-nominatif', false);
    }

    /** Dulu hanya tanda tangan daftar riil yang tercatat, rincian biaya tidak. */
    public function test_riwayat_memuat_tanda_tangan_rincian_biaya_tanpa_daftar_riil(): void
    {
        DaftarRiil::query()->update(['ditandatangani_at' => null, 'id_ppk' => null, 'kode_verifikasi' => null]);

        $this->actingAs($this->ppk)
            ->get(route('persetujuan.riwayat'))
            ->assertOk()
            ->assertSee('Rincian Biaya Perjalanan Dinas')
            ->assertDontSee('Daftar Pengeluaran Riil')
            ->assertViewHas('jumlah', fn (array $jumlah) => $jumlah['berkas'] === 1 && $jumlah['nominatif'] === 1);
    }
}
