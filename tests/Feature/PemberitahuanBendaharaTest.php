<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\Notifikasi;
use App\Models\PesertaUsulan;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bendahara diberi tahu pada dua titik: rincian biaya selesai disusun
 * sehingga uang muka 80% siap ditransfer, dan berkas pertanggungjawaban
 * lengkap sehingga pelunasan dapat diproses.
 */
class PemberitahuanBendaharaTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    private User $timKeuangan;

    private User $pelaksana;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->bendahara = User::factory()->create(['role' => PeranPengguna::Bendahara->value]);
        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
        $this->pelaksana = User::factory()->create([
            'role' => PeranPengguna::DosenTendik->value,
            'nama' => 'Rahmatullah Pontoh',
        ]);

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);
    }

    private function tambahKomponen(): void
    {
        $this->actingAs($this->timKeuangan)
            ->post(route('keuangan.rincian.store', $this->usulan), [
                'kategori' => KategoriBiaya::UangHarian->value,
                'komponen' => 'Uang harian',
                'volume' => 3,
                'satuan' => 'OH',
                'harga_satuan' => 530_000,
            ]);
    }

    /**
     * @param  array<string, mixed>  $berkas
     */
    private function lampirkan(array $berkas): void
    {
        Dokumen::where('id_usulan', $this->usulan->id)->delete();

        Dokumen::factory()->create([
            'id_usulan' => $this->usulan->id,
            ...array_fill_keys(Usulan::DOKUMEN_LPJ_WAJIB, null),
            ...$berkas,
        ]);
    }

    /**
     * Selesaikan laporan lewat jalur aplikasinya, bukan dengan menulis
     * langsung ke basis data — inilah tindakan terakhir yang membuat
     * berkas terhitung lengkap.
     */
    private function selesaikanLaporan(): void
    {
        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.update', $this->usulan), [
                'id_status_hasil' => StatusHasil::first()->id,
                'kegiatan' => [
                    Carbon::parse($this->usulan->tanggal_mulai)->toDateString() => 'Mengikuti rapat koordinasi.',
                ],
                'tindak_lanjut' => [['uraian' => 'Menyusun laporan internal.']],
            ]);

        $this->actingAs($this->pelaksana)
            ->put(route('dokumen.laporan.selesaikan', $this->usulan));
    }

    // ── Rincian siap dibayar 80% ──

    public function test_bendahara_diberi_tahu_saat_rincian_tersusun(): void
    {
        $this->tambahKomponen();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->bendahara->id,
            'id_usulan' => $this->usulan->id,
            'judul' => 'Rincian biaya siap dibayar 80%',
        ]);
    }

    public function test_pemberitahuan_menyebut_nominal_uang_mukanya(): void
    {
        $this->tambahKomponen();

        $pesan = Notifikasi::where('judul', 'Rincian biaya siap dibayar 80%')->value('pesan');

        $this->assertStringContainsString('1.590.000', $pesan);
        $this->assertStringContainsString('1.272.000', $pesan);
        $this->assertStringContainsString('Rahmatullah Pontoh', $pesan);
    }

    public function test_pemberitahuan_tidak_diulang_saat_komponen_ditambah_lagi(): void
    {
        $this->tambahKomponen();
        $this->tambahKomponen();

        $this->assertSame(
            1,
            Notifikasi::where('judul', 'Rincian biaya siap dibayar 80%')->count()
        );
    }

    public function test_tidak_diberitahu_bila_uang_muka_sudah_cair(): void
    {
        $this->usulan->keuangan->update([
            'tanggal_transfer' => today(),
            'status' => Keuangan::STATUS_SEBAGIAN,
        ]);

        $this->tambahKomponen();

        $this->assertDatabaseMissing('notifikasi', ['judul' => 'Rincian biaya siap dibayar 80%']);
    }

    public function test_hanya_bendahara_yang_menerima(): void
    {
        $this->tambahKomponen();

        $penerima = Notifikasi::where('judul', 'Rincian biaya siap dibayar 80%')->pluck('id_user');

        $this->assertSame([$this->bendahara->id], $penerima->all());
    }

    // ── Berkas lengkap ──

    public function test_bendahara_diberi_tahu_saat_berkas_lengkap(): void
    {
        // Seluruh berkas sudah ada kecuali laporannya.
        $this->lengkapiPertanggungjawaban($this->usulan);
        $this->usulan->laporan->update(['diselesaikan_at' => null]);

        $this->selesaikanLaporan();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->bendahara->id,
            'id_usulan' => $this->usulan->id,
            'judul' => 'Berkas pertanggungjawaban lengkap',
        ]);
    }

    public function test_tidak_diberitahu_selama_berkas_belum_lengkap(): void
    {
        // Hanya SPPD yang ada; tiket, nota, dan berkas lain belum.
        $this->lampirkan(['sppd' => 'dokumen/sppd.pdf']);

        $this->selesaikanLaporan();

        $this->assertDatabaseMissing('notifikasi', ['judul' => 'Berkas pertanggungjawaban lengkap']);
    }

    // ── Perjadin lengkap dan ditandatangani PPK ──

    /**
     * Berkas lengkap dan rincian biaya sudah ditandatangani PPK; tinggal
     * daftar pengeluaran riilnya yang menunggu tanda tangan.
     */
    private function siapkanTandaTanganTerakhir(): PesertaUsulan
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        $peserta = $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->tandatanganiBerkas($this->usulan->fresh())->update(['ditandatangani_at' => null, 'id_ppk' => null]);

        return $peserta;
    }

    public function test_bendahara_diberi_tahu_saat_ppk_menandatangani_berkas_lengkap(): void
    {
        $peserta = $this->siapkanTandaTanganTerakhir();

        $this->actingAs(User::factory()->ppk()->create())
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $peserta]))
            ->assertSessionHas('success');

        $pesan = Notifikasi::where('id_user', $this->bendahara->id)
            ->where('judul', 'Perjadin lengkap, segera dibayarkan')
            ->first();

        $this->assertNotNull($pesan);
        $this->assertStringContainsString('ditandatangani PPK', $pesan->pesan);
        $this->assertStringContainsString('transport lokal Rp 150.000', $pesan->pesan);
        $this->assertSame(route('keuangan.detail', $this->usulan->no_usulan), $pesan->url);
    }

    public function test_berkas_yang_baru_lengkap_sesudah_tanda_tangan_ppk_langsung_siap_dibayar(): void
    {
        $peserta = $this->siapkanTandaTanganTerakhir();
        $this->usulan->laporan->update(['diselesaikan_at' => null]);

        $this->actingAs(User::factory()->ppk()->create())
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $peserta]));

        $this->assertDatabaseMissing('notifikasi', ['judul' => 'Perjadin lengkap, segera dibayarkan']);

        $this->selesaikanLaporan();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->bendahara->id,
            'judul' => 'Perjadin lengkap, segera dibayarkan',
        ]);
        $this->assertDatabaseMissing('notifikasi', ['judul' => 'Berkas pertanggungjawaban lengkap']);
    }

    public function test_tidak_diberitahu_bila_sudah_lunas(): void
    {
        $this->usulan->keuangan->update([
            'status' => Keuangan::STATUS_LUNAS,
            'tanggal_pelunasan' => today(),
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);
        $this->usulan->laporan->update(['diselesaikan_at' => null]);

        $this->selesaikanLaporan();

        $this->assertDatabaseMissing('notifikasi', ['judul' => 'Berkas pertanggungjawaban lengkap']);
    }

    // ── Penanda pada menu Pembayaran ──

    public function test_tahap_uang_muka_menandai_berkas_lengkap(): void
    {
        $this->usulan->keuangan->update([
            'tanggal_transfer' => today(),
            'status' => Keuangan::STATUS_SEBAGIAN,
        ]);

        $this->lengkapiPertanggungjawaban($this->usulan);

        $this->actingAs($this->bendahara)
            ->get(route('pembayaran', ['tahap' => 'uang-muka']))
            ->assertOk()
            ->assertSee('Dokumen lengkap');
    }

    public function test_tahap_uang_muka_menandai_berkas_yang_kurang(): void
    {
        $this->usulan->keuangan->update([
            'tanggal_transfer' => today(),
            'status' => Keuangan::STATUS_SEBAGIAN,
        ]);

        $this->lampirkan(['sppd' => 'dokumen/sppd.pdf']);

        $this->actingAs($this->bendahara)
            ->get(route('pembayaran', ['tahap' => 'uang-muka']))
            ->assertOk()
            ->assertSee('Kurang 5 berkas')
            ->assertDontSee('Dokumen lengkap');
    }
}
