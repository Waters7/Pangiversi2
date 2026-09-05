<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\RiwayatPembayaran;
use App\Models\User;
use App\Models\Usulan;
use App\Services\SinkronBiayaDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Keutuhan berkas yang sudah ditandatangani, dan ketepatan pencatatan uang.
 *
 * Tanda tangan menyatakan persetujuan atas angka tertentu. Setelah dibubuhkan,
 * angka itu tidak boleh berubah lewat pintu mana pun — unggahan pelaksana,
 * penyuntingan tim keuangan, maupun penyelarasan otomatis. Pembayaran pun
 * hanya boleh tercatat sekali, dan koreksinya lewat pembatalan bercatatan,
 * bukan menimpa.
 */
class PenguncianBerkasTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    private User $timKeuangan;

    private User $bendahara;

    private User $ppk;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        $this->bendahara = User::factory()->create(['role' => User::ROLE_BENDAHARA]);
        $this->ppk = User::factory()->ppk()->create();

        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'no_tugas' => 'KP.03.01/F.XXXVIII/55/2026',
            'status' => StatusUsulan::Disetujui->value,
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $this->usulan->id]);

        $this->peserta = $this->usulan->peserta()->create([
            'id_user' => $this->pelaksana->id,
            'nama' => $this->pelaksana->nama,
            'nip' => $this->pelaksana->nip,
            'peran' => 'ketua',
        ]);
    }

    /** Berkas sudah terisi, tervalidasi, dan dikirim ke pelaksana. */
    private function berkasSampaiKePelaksana(): DaftarRiil
    {
        $this->lengkapiPertanggungjawaban($this->usulan);
        app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());
        $this->validasiSeluruhNominal($this->usulan, $this->timKeuangan);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));

        return DaftarRiil::firstWhere('id_usulan', $this->usulan->id)->fresh();
    }

    private function rincianDokumen(): RincianBiaya
    {
        return $this->usulan->fresh('keuangan.rincianBiaya')->keuangan->rincianBiaya
            ->firstWhere('sumber', RincianBiaya::SUMBER_DOKUMEN);
    }

    private function suntingRincian(RincianBiaya $baris): TestResponse
    {
        return $this->actingAs($this->timKeuangan)
            ->put(route('keuangan.rincian.update', [$this->usulan, $baris]), [
                'kategori' => $baris->kategori->value,
                'komponen' => $baris->komponen,
                'volume' => 1,
                'satuan' => $baris->satuan,
                'harga_satuan' => 99_000_000,
            ]);
    }

    // ── Penguncian rincian biaya ──

    public function test_rincian_terbuka_selama_belum_ditandatangani(): void
    {
        $this->berkasSampaiKePelaksana();

        $this->suntingRincian($this->rincianDokumen())->assertSessionHasNoErrors();

        $this->assertSame(99_000_000.0, (float) $this->rincianDokumen()->harga_satuan);
    }

    public function test_rincian_terkunci_setelah_ditandatangani_pelaksana(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalurRincian()->setujui();

        $nilaiLama = (float) $this->rincianDokumen()->jumlah;

        $this->suntingRincian($this->rincianDokumen())->assertForbidden();

        $this->assertSame($nilaiLama, (float) $this->rincianDokumen()->jumlah);
    }

    public function test_rincian_terkunci_setelah_ditandatangani_ppk(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalurRincian()->setujui();
        $berkas->fresh()->jalurRincian()->tandaTangani($this->ppk);

        $this->suntingRincian($this->rincianDokumen())->assertForbidden();
    }

    public function test_komponen_tidak_dapat_ditambah_atau_dihapus_pada_berkas_terkunci(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalurRincian()->setujui();

        $baris = $this->rincianDokumen();

        $this->actingAs($this->timKeuangan)
            ->post(route('keuangan.rincian.store', $this->usulan), [
                'kategori' => 'lainnya',
                'komponen' => 'Biaya tambahan',
                'volume' => 1,
                'satuan' => 'paket',
                'harga_satuan' => 500_000,
            ])
            ->assertForbidden();

        $this->actingAs($this->timKeuangan)
            ->delete(route('keuangan.rincian.destroy', [$this->usulan, $baris]))
            ->assertForbidden();

        $this->assertDatabaseHas('rincian_biayas', ['id' => $baris->id]);
    }

    // ── Penguncian unggahan pelaksana ──

    public function test_pelaksana_tidak_dapat_mengubah_berkas_yang_sudah_ditandatangani(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalur()->setujui();

        $this->actingAs($this->pelaksana)
            ->post(route('dokumen.store', $this->usulan), [
                'section' => 'penugasan',
                'sppd' => UploadedFile::fake()->create('sppd-baru.pdf', 40, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    /**
     * Lapis kedua: meski penyelarasan terpanggil dari jalur lain, rincian
     * yang sudah ditandatangani tidak ditulis ulang.
     */
    public function test_penyelarasan_tidak_menimpa_rincian_yang_terkunci(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalurRincian()->setujui();

        $sebelum = $this->usulan->fresh('keuangan.rincianBiaya')->keuangan->total;

        $this->usulan->tiket()->update(['harga' => 9_000_000]);
        $hasil = app(SinkronBiayaDokumen::class)->selaraskan($this->usulan->fresh());

        $this->assertSame(['ditambah' => 0, 'diperbarui' => 0, 'dihapus' => 0], $hasil);
        $this->assertSame(
            (float) $sebelum,
            (float) $this->usulan->fresh('keuangan')->keuangan->total,
            'Total berubah padahal rinciannya sudah ditandatangani.'
        );
    }

    public function test_berkas_terbuka_kembali_setelah_dikirim_ulang(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalurRincian()->setujui();

        // Tim keuangan mengirim ulang, sikap pelaksana tercabut, kuncinya terbuka.
        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertSessionHas('success');

        $this->suntingRincian($this->rincianDokumen())->assertSessionHasNoErrors();
    }

    public function test_kirim_ulang_tertutup_setelah_ppk_menandatangani(): void
    {
        $berkas = $this->berkasSampaiKePelaksana();
        $berkas->jalurRincian()->setujui();
        $berkas->fresh()->jalurRincian()->tandaTangani($this->ppk);

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]))
            ->assertForbidden();
    }

    // ── Ketepatan pencatatan pembayaran ──

    private function bayarUangMuka(string $tanggal = '2026-04-04'): TestResponse
    {
        return $this->actingAs($this->bendahara)
            ->post(route('keuangan.bayar-uang-muka', $this->usulan), [
                'tanggal_transfer' => $tanggal,
                'bukti_transfer' => UploadedFile::fake()->create('um.pdf', 40, 'application/pdf'),
            ]);
    }

    private function siapkanNominal(): void
    {
        $this->usulan->keuangan->update(['total' => 5_000_000, 'uang_muka' => 4_000_000, 'sisa' => 1_000_000]);
    }

    public function test_uang_muka_hanya_tercatat_sekali(): void
    {
        $this->siapkanNominal();

        $this->bayarUangMuka('2026-04-04');
        $this->bayarUangMuka('2026-04-09')->assertSessionHas('error');

        $this->assertSame(
            1,
            RiwayatPembayaran::where('jenis', RiwayatPembayaran::JENIS_UANG_MUKA)->count(),
            'Pembayaran kedua ikut tercatat pada jurnal.'
        );

        // Tanggal yang pertama tidak tertimpa.
        $this->assertSame(
            '2026-04-04',
            $this->usulan->fresh('keuangan')->keuangan->tanggal_transfer->toDateString()
        );
    }

    public function test_pembatalan_uang_muka_menambah_baris_jurnal(): void
    {
        $this->siapkanNominal();
        $this->bayarUangMuka();

        $this->actingAs($this->bendahara)
            ->put(route('keuangan.batal-uang-muka', $this->usulan), [
                'alasan' => 'Tanggal transfer salah ketik, seharusnya 6 April 2026.',
            ])
            ->assertSessionHas('success');

        $batal = RiwayatPembayaran::firstWhere('jenis', RiwayatPembayaran::JENIS_BATAL_UANG_MUKA);

        $this->assertNotNull($batal, 'Pembatalan tidak tercatat pada jurnal.');
        $this->assertSame($this->bendahara->id, $batal->id_pencatat);
        $this->assertNotEmpty($batal->catatan, 'Alasan pembatalan tidak tersimpan.');

        // Baris pembayaran aslinya tetap ada — jejaknya tidak dihapus.
        $this->assertTrue(RiwayatPembayaran::where('jenis', RiwayatPembayaran::JENIS_UANG_MUKA)->exists());

        $keuangan = $this->usulan->fresh('keuangan')->keuangan;
        $this->assertNull($keuangan->tanggal_transfer);
        $this->assertSame(Keuangan::STATUS_BELUM, $keuangan->status);
    }

    public function test_pembatalan_wajib_beralasan(): void
    {
        $this->siapkanNominal();
        $this->bayarUangMuka();

        $this->actingAs($this->bendahara)
            ->put(route('keuangan.batal-uang-muka', $this->usulan), ['alasan' => 'salah'])
            ->assertSessionHasErrors('alasan');

        $this->assertNotNull($this->usulan->fresh('keuangan')->keuangan->tanggal_transfer);
    }

    public function test_pembayaran_dapat_dicatat_ulang_setelah_dibatalkan(): void
    {
        $this->siapkanNominal();
        $this->bayarUangMuka('2026-04-04');

        $this->actingAs($this->bendahara)
            ->put(route('keuangan.batal-uang-muka', $this->usulan), [
                'alasan' => 'Tanggal transfer salah ketik, seharusnya 6 April 2026.',
            ]);

        $this->bayarUangMuka('2026-04-06')->assertSessionHas('success');

        $this->assertSame(
            '2026-04-06',
            $this->usulan->fresh('keuangan')->keuangan->tanggal_transfer->toDateString()
        );
    }

    public function test_uang_muka_tidak_dapat_dibatalkan_setelah_lunas(): void
    {
        $this->siapkanNominal();
        $this->bayarUangMuka();
        $this->usulan->keuangan->update(['status' => Keuangan::STATUS_LUNAS, 'tanggal_pelunasan' => '2026-04-20']);

        $this->actingAs($this->bendahara)
            ->put(route('keuangan.batal-uang-muka', $this->usulan), [
                'alasan' => 'Tanggal transfer salah ketik, seharusnya 6 April 2026.',
            ])
            ->assertForbidden();
    }

    public function test_pembatalan_pelunasan_mencabut_kode_konfirmasi_bendahara(): void
    {
        $this->siapkanNominal();
        $keuangan = $this->usulan->keuangan;
        $keuangan->update([
            'status' => Keuangan::STATUS_LUNAS,
            'tanggal_transfer' => '2026-04-04',
            'tanggal_pelunasan' => '2026-04-20',
        ]);
        $keuangan->konfirmasiPelunasan();

        $this->assertNotNull($keuangan->fresh()->kode_konfirmasi_bayar);

        $this->actingAs($this->bendahara)
            ->put(route('keuangan.batal-pelunasan', $this->usulan), [
                'alasan' => 'Transfer pelunasan ditolak bank karena rekening tidak aktif.',
            ])
            ->assertSessionHas('success');

        $segar = $keuangan->fresh();

        // QR pada dokumen yang beredar tidak boleh lagi menyatakan lunas.
        $this->assertNull($segar->kode_konfirmasi_bayar);
        $this->assertNull($segar->tanggal_pelunasan);
        $this->assertSame(Keuangan::STATUS_SEBAGIAN, $segar->status);
        $this->assertTrue(RiwayatPembayaran::where('jenis', RiwayatPembayaran::JENIS_BATAL_PELUNASAN)->exists());
    }

    public function test_pembatalan_tertutup_bagi_peran_tanpa_hak_pembayaran(): void
    {
        $this->siapkanNominal();
        $this->bayarUangMuka();

        $this->actingAs($this->pelaksana)
            ->put(route('keuangan.batal-uang-muka', $this->usulan), [
                'alasan' => 'Saya merasa nominalnya keliru.',
            ])
            ->assertForbidden();
    }
}
