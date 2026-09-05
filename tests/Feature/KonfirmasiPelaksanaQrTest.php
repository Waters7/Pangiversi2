<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tanda tangan pelaksana pada daftar pengeluaran riil juga diberi legalitas:
 * QR-nya menuju halaman yang menampilkan nomor perjadin dan kode konfirmasi.
 */
class KonfirmasiPelaksanaQrTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    private PesertaUsulan $peserta;

    private User $pelaksana;

    private User $timKeuangan;

    private User $ppk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create([
            'role' => PeranPengguna::DosenTendik->value,
            'nama' => 'Rahmatullah Pontoh',
        ]);
        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
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

    private function dikirimKePelaksana(float $nominal = 1_250_000): DaftarRiil
    {
        $daftar = DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $this->peserta->id,
            'total_riil' => $nominal,

            // Transport lokal sudah diperiksa tim keuangan; tanpa itu berkas
            // tidak boleh dikirim maupun dikirim ulang.
            'divalidasi_at' => now(),
            'id_validator' => $this->timKeuangan->id,
        ]);

        $daftar->kirimKePegawai();

        return $daftar->fresh();
    }

    private function pelaksanaMenyetujui(): DaftarRiil
    {
        $this->dikirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]));

        return DaftarRiil::firstWhere('id_peserta', $this->peserta->id);
    }

    // ── Penerbitan kode ──

    public function test_persetujuan_pelaksana_menerbitkan_kode_konfirmasi(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->assertNotNull($daftar->kode_konfirmasi);
        $this->assertStringStartsWith('PLK-', $daftar->kode_konfirmasi);
    }

    public function test_kode_belum_terbit_sebelum_pelaksana_menyetujui(): void
    {
        $this->assertNull($this->dikirimKePelaksana()->kode_konfirmasi);
    }

    public function test_kode_konfirmasi_berbeda_dengan_kode_verifikasi_ppk(): void
    {
        $this->pelaksanaMenyetujui();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        $daftar = DaftarRiil::firstWhere('id_peserta', $this->peserta->id);

        $this->assertNotSame($daftar->kode_konfirmasi, $daftar->kode_verifikasi);
        $this->assertStringStartsWith('PPK-', $daftar->kode_verifikasi);
    }

    public function test_kode_konfirmasi_bersifat_unik_antar_peserta(): void
    {
        $pertama = $this->pelaksanaMenyetujui();

        $pesertaLain = PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_user' => User::factory()->create()->id,
        ]);
        $daftarLain = DaftarRiil::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_peserta' => $pesertaLain->id,
            'total_riil' => 800_000,
        ]);
        $daftarLain->kirimKePegawai();
        $daftarLain->setujuiPegawai();

        $this->assertNotSame($pertama->kode_konfirmasi, $daftarLain->fresh()->kode_konfirmasi);
    }

    public function test_kode_bertahan_saat_pelaksana_menyetujui_ulang(): void
    {
        $daftar = $this->pelaksanaMenyetujui();
        $kodeAwal = $daftar->kode_konfirmasi;

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.setuju', [$this->usulan, $this->peserta]));

        $this->assertSame($kodeAwal, $daftar->fresh()->kode_konfirmasi);
    }

    // ── Pencabutan kode ──

    public function test_rincian_yang_disanggah_tidak_menerbitkan_kode(): void
    {
        $daftar = $this->dikirimKePelaksana();

        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Uang penginapan malam kedua belum dihitung.',
            ])
            ->assertSessionHas('success');

        $this->assertNull($daftar->fresh()->kode_konfirmasi);
    }

    public function test_kirim_ulang_dari_tim_keuangan_mencabut_kode_konfirmasi(): void
    {
        $daftar = $this->pelaksanaMenyetujui();
        $kode = $daftar->kode_konfirmasi;

        $this->actingAs($this->timKeuangan)
            ->put(route('daftar-riil.kirim-pegawai', [$this->usulan, $this->peserta]));

        $this->assertNull($daftar->fresh()->kode_konfirmasi);

        // Dokumen yang terlanjur tercetak tidak lagi tervalidasi.
        $this->get(route('verifikasi.tampil', $kode))
            ->assertOk()
            ->assertSee('Dokumen Tidak Terverifikasi');
    }

    public function test_kode_bertahan_karena_persetujuan_menutup_masa_sanggah(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        // Setelah menyatakan setuju, pelaksana tidak dapat berbalik menyanggah.
        $this->actingAs($this->pelaksana)
            ->put(route('daftar-riil.sanggah', [$this->usulan, $this->peserta]), [
                'sanggahan' => 'Ternyata masih ada komponen yang terlewat.',
            ])
            ->assertForbidden();

        $this->assertNotNull($daftar->fresh()->kode_konfirmasi);
    }

    // ── Halaman verifikasi publik ──

    public function test_halaman_verifikasi_terbuka_tanpa_login(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->get(route('verifikasi.tampil', $daftar->kode_konfirmasi))
            ->assertOk()
            ->assertSee('Dokumen Terverifikasi');
    }

    public function test_halaman_menampilkan_nomor_perjadin_dan_kode_konfirmasi(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->get(route('verifikasi.tampil', $daftar->kode_konfirmasi))
            ->assertOk()
            ->assertSee($this->usulan->no_usulan)
            ->assertSee($daftar->kode_konfirmasi)
            ->assertSee('Kode Konfirmasi')
            ->assertSee('Tanggal Konfirmasi')
            ->assertSee('Pelaksana Perjalanan Dinas')
            ->assertSee($this->peserta->nama);
    }

    public function test_halaman_tidak_membocorkan_nominal_biaya(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->get(route('verifikasi.tampil', $daftar->kode_konfirmasi))
            ->assertOk()
            ->assertDontSee('1.250.000');
    }

    public function test_kode_ppk_tetap_dikenali_sebagai_tanda_tangan_ppk(): void
    {
        $this->pelaksanaMenyetujui();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        $daftar = DaftarRiil::firstWhere('id_peserta', $this->peserta->id);

        $this->get(route('verifikasi.tampil', $daftar->kode_verifikasi))
            ->assertOk()
            ->assertSee('Pejabat Pembuat Komitmen')
            ->assertSee('Tanggal Verifikasi')
            ->assertSee($this->ppk->nama);
    }

    // ── QR pada PDF ──

    public function test_url_konfirmasi_kosong_selama_belum_disetujui(): void
    {
        $this->assertNull($this->dikirimKePelaksana()->urlKonfirmasi());
    }

    public function test_url_konfirmasi_menunjuk_halaman_verifikasi(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->assertSame(
            route('verifikasi.tampil', $daftar->kode_konfirmasi),
            $daftar->urlKonfirmasi()
        );
    }

    public function test_pdf_memuat_kode_konfirmasi_setelah_disetujui(): void
    {
        $this->pelaksanaMenyetujui();

        $this->actingAs($this->timKeuangan)
            ->get(route('daftar-riil.cetak', [$this->usulan, $this->peserta]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_dokumen_cetak_memuat_qr_pada_kedua_tanda_tangan(): void
    {
        $this->pelaksanaMenyetujui();

        $this->actingAs($this->ppk)
            ->put(route('daftar-riil.tanda-tangan', [$this->usulan, $this->peserta]));

        $html = $this->renderDokumen();

        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringContainsString('Pejabat Pembuat Komitmen', $html);
        $this->assertStringContainsString('Yang Melakukan Perjalanan Dinas', $html);
        $this->assertStringContainsString('Pindai QR untuk memeriksa nomor perjadin', $html);
    }

    public function test_dokumen_cetak_tanpa_persetujuan_tidak_memuat_qr_pelaksana(): void
    {
        $this->dikirimKePelaksana();

        $html = $this->renderDokumen();

        $this->assertStringNotContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('Nominal dikonfirmasi secara elektronik', $html);
    }

    /**
     * Render templat cetaknya langsung agar isi tanda tangan dapat diperiksa
     * tanpa harus membongkar berkas PDF.
     */
    private function renderDokumen(): string
    {
        $daftar = DaftarRiil::with('ppk')->firstWhere('id_peserta', $this->peserta->id);
        $qrCode = app(QrCodeService::class);

        return view('daftar-riil.cetak', [
            'usulan' => $this->usulan->load('user.unit', 'kegiatan', 'keuangan.rincianBiaya'),
            'peserta' => $this->peserta,
            'daftar' => $daftar,
            'qr' => $daftar->urlVerifikasi() ? $qrCode->dataUri($daftar->urlVerifikasi()) : null,
            'qrPelaksana' => $daftar->urlKonfirmasi() ? $qrCode->dataUri($daftar->urlKonfirmasi()) : null,
        ])->render();
    }

    // ── Tampilan dalam aplikasi ──

    public function test_kode_konfirmasi_tampil_pada_halaman_rincian_saya(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->actingAs($this->pelaksana)
            ->get(route('rincian-saya.daftar-riil'))
            ->assertOk()
            ->assertSee($daftar->kode_konfirmasi);
    }

    public function test_kode_konfirmasi_tampil_untuk_tim_keuangan(): void
    {
        $daftar = $this->pelaksanaMenyetujui();

        $this->actingAs($this->timKeuangan)
            ->get(route('daftar-riil.show', $this->usulan))
            ->assertOk()
            ->assertSee($daftar->kode_konfirmasi);
    }
}
