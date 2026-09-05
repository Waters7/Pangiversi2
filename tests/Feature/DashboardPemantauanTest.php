<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\Keuangan;
use App\Models\Pengaturan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard pengguna dipakai untuk memantau jadwal perjalanan, batas
 * penyerahan berkas pertanggungjawaban, dan sampai mana bendahara memproses
 * pembayarannya.
 *
 * Ringkasan persetujuan tidak lagi ada di sini: penugasan disahkan lewat SPD,
 * jadi usulan tidak menunggu keputusan siapa pun.
 */
class DashboardPemantauanTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    /**
     * Perjalanan yang sudah pasti berlangsung, lengkap dengan tanggalnya.
     */
    private function perjalanan(string $mulai, string $selesai, array $ubahan = []): Usulan
    {
        return Usulan::factory()->create(array_merge([
            'id_user' => $this->pengguna->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
        ], $ubahan));
    }

    /**
     * Lengkapi seluruh berkas pertanggungjawaban wajib sebuah usulan.
     */
    private function lengkapiBerkas(Usulan $usulan): Dokumen
    {
        return Dokumen::create(array_merge(
            ['id_usulan' => $usulan->id, 'surat_tugas' => 'surat-tugas.pdf'],
            array_fill_keys(Usulan::DOKUMEN_LPJ_WAJIB, 'berkas.pdf'),
        ));
    }

    private function isiDashboard(): string
    {
        return $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
    }

    // ── Persetujuan sudah ditiadakan ──

    public function test_kartu_persetujuan_tidak_lagi_ditampilkan(): void
    {
        $this->perjalanan(today()->addWeek()->toDateString(), today()->addWeek()->addDays(2)->toDateString());

        $isi = $this->isiDashboard();

        $this->assertStringNotContainsString('Menunggu Persetujuan', $isi);
        $this->assertStringNotContainsString('Ringkasan Status', $isi);
    }

    public function test_menu_persetujuan_dihapus_dari_sidebar(): void
    {
        // Diperiksa dengan akun PPK: hanya dia yang dulu melihat menu ini.
        $ppk = User::factory()->ppk()->create();

        $isi = $this->actingAs($ppk)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('persetujuan').'"', $isi);
    }

    public function test_submenu_pratinjau_cetak_dihapus(): void
    {
        $this->assertStringNotContainsString('Pratinjau Cetak', $this->isiDashboard());
    }

    // ── Pemantauan jadwal ──

    public function test_perjalanan_mendatang_tampil_dengan_hitungan_mundur(): void
    {
        $this->perjalanan(
            today()->addDays(5)->toDateString(),
            today()->addDays(7)->toDateString(),
            ['lokasi' => 'Jakarta'],
        );

        $isi = $this->isiDashboard();

        $this->assertStringContainsString('Jadwal Perjalanan Saya', $isi);
        $this->assertStringContainsString('Berangkat 5 hari lagi', $isi);
        $this->assertStringContainsString('Jakarta', $isi);
    }

    public function test_perjalanan_yang_sedang_berlangsung_ditandai(): void
    {
        $this->perjalanan(
            today()->subDay()->toDateString(),
            today()->addDay()->toDateString(),
        );

        $this->assertStringContainsString('Sedang berjalan', $this->isiDashboard());
    }

    public function test_dashboard_menyebut_batas_laporan_sejak_sebelum_berangkat(): void
    {
        $this->perjalanan(
            today()->addDays(2)->toDateString(),
            today()->addDays(4)->toDateString(),
        );

        $isi = $this->isiDashboard();

        // Tenggang bawaan 3 hari, jadi batasnya H+3 setelah perjalanan berakhir.
        $this->assertStringContainsString('Batas laporan H+3', $isi);
        $this->assertStringContainsString(today()->addDays(7)->translatedFormat('d M Y'), $isi);
    }

    public function test_tanpa_perjalanan_terjadwal_dashboard_menyatakannya(): void
    {
        $this->assertStringContainsString(
            'Tidak ada perjalanan dinas yang dijadwalkan',
            $this->isiDashboard()
        );
    }

    // ── Pengingat batas laporan ──

    public function test_pengingat_muncul_setelah_perjalanan_berakhir(): void
    {
        $usulan = $this->perjalanan(
            today()->subDays(3)->toDateString(),
            today()->subDay()->toDateString(),
        );

        $isi = $this->isiDashboard();

        $this->assertStringContainsString('laporan perjalanan dinas belum lengkap', $isi);
        $this->assertStringContainsString($usulan->no_usulan, $isi);
        // Berakhir kemarin, tenggang 3 hari: masih tersisa 2 hari.
        $this->assertStringContainsString('Sisa 2 hari', $isi);
    }

    public function test_pengingat_menandai_keterlambatan(): void
    {
        $this->perjalanan(
            today()->subDays(14)->toDateString(),
            today()->subDays(10)->toDateString(),
        );

        $isi = $this->isiDashboard();

        $this->assertStringContainsString('melewati batas', $isi);
        $this->assertStringContainsString('Terlambat 7 hari', $isi);
    }

    public function test_pengingat_menjelaskan_konsekuensinya(): void
    {
        $this->perjalanan(
            today()->subDays(3)->toDateString(),
            today()->subDay()->toDateString(),
        );

        $isi = $this->isiDashboard();

        $this->assertStringContainsString('Bila lewat batas:', $isi);
        $this->assertStringContainsString('Pelunasan sisa 20% tertahan', $isi);
    }

    public function test_pengingat_hilang_setelah_berkas_lengkap(): void
    {
        $usulan = $this->perjalanan(
            today()->subDays(3)->toDateString(),
            today()->subDay()->toDateString(),
        );

        $this->lengkapiPertanggungjawaban($usulan);

        $this->assertStringNotContainsString('laporan perjalanan dinas belum lengkap', $this->isiDashboard());
    }

    public function test_batas_laporan_mengikuti_tenggang_yang_diatur_administrator(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => '5']);

        $this->perjalanan(
            today()->subDays(3)->toDateString(),
            today()->subDay()->toDateString(),
        );

        $isi = $this->isiDashboard();

        // Angka yang ditampilkan harus sama dengan yang benar-benar ditagih.
        $this->assertStringContainsString('Perlu Laporan (H+5)', $isi);
        $this->assertStringContainsString('Sisa 4 hari', $isi);
    }

    // ── Status pembayaran oleh bendahara ──

    public function test_status_pembayaran_ditampilkan_sebagai_kerja_bendahara(): void
    {
        $usulan = $this->perjalanan(
            today()->addDays(3)->toDateString(),
            today()->addDays(5)->toDateString(),
        );

        Keuangan::factory()->lunas()->create(['id_usulan' => $usulan->id]);

        $isi = $this->isiDashboard();

        $this->assertStringContainsString('Status Pembayaran', $isi);
        $this->assertStringContainsString('Diproses oleh Bendahara', $isi);
        $this->assertStringContainsString('Lunas 100%', $isi);
    }

    public function test_uang_muka_dan_belum_bayar_terhitung_terpisah(): void
    {
        $uangMuka = $this->perjalanan(
            today()->addDays(3)->toDateString(),
            today()->addDays(5)->toDateString(),
        );
        Keuangan::factory()->uangMuka()->create(['id_usulan' => $uangMuka->id]);

        $belum = $this->perjalanan(
            today()->addDays(8)->toDateString(),
            today()->addDays(9)->toDateString(),
        );
        Keuangan::factory()->belumBayar()->create(['id_usulan' => $belum->id]);

        $isi = $this->isiDashboard();

        $this->assertStringContainsString('Uang Muka', $isi);
        $this->assertStringContainsString('Belum Bayar', $isi);
    }

    public function test_perjalanan_tanpa_catatan_keuangan_dihitung_belum_bayar(): void
    {
        // Justru ini yang perlu dipantau pengusul: bendahara belum menyentuhnya.
        $this->perjalanan(
            today()->addDays(3)->toDateString(),
            today()->addDays(5)->toDateString(),
        );

        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('pembayaran', fn (array $ringkasan) => $ringkasan['belum'] === 1
                && $ringkasan['total'] === 1);
    }

    public function test_tanpa_perjalanan_panel_pembayaran_kosong(): void
    {
        $this->assertStringContainsString(
            'Belum ada perjalanan dinas yang masuk proses pembayaran',
            $this->isiDashboard()
        );
    }

    public function test_draf_belum_dihitung_sebagai_perjalanan(): void
    {
        $this->perjalanan(
            today()->addDays(3)->toDateString(),
            today()->addDays(5)->toDateString(),
            ['status' => StatusUsulan::Draft->value],
        );

        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('akanBerangkat', fn ($daftar) => $daftar->isEmpty())
            ->assertViewHas('pembayaran', fn (array $ringkasan) => $ringkasan['total'] === 0);
    }

    public function test_perjalanan_pegawai_lain_tidak_ikut_terpantau(): void
    {
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        Usulan::factory()->create([
            'id_user' => $orangLain->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->addDays(3)->toDateString(),
            'tanggal_selesai' => today()->addDays(5)->toDateString(),
            'lokasi' => 'Surabaya Rahasia',
        ]);

        $this->assertStringNotContainsString('Surabaya Rahasia', $this->isiDashboard());
    }
}
