<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Dokumen;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use App\Models\User;
use App\Models\Usulan;
use App\Services\PengingatDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengingat kelengkapan berkas: tenggang, jeda ulang, dan batasnya diatur
 * Super Administrator lewat menu Administrasi Sistem.
 */
class PengingatDokumenTest extends TestCase
{
    use RefreshDatabase;

    private User $pelaksana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => PeranPengguna::SuperAdministrator->value]);
    }

    /**
     * Perjalanan yang sudah berakhir sekian hari lalu, berkasnya belum lengkap.
     */
    private function perjadinSelesai(int $hariLalu, array $berkas = []): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => today()->subDays($hariLalu + 2),
            'tanggal_selesai' => today()->subDays($hariLalu),
        ]);

        Dokumen::factory()->create([
            'id_usulan' => $usulan->id,
            ...array_fill_keys(Usulan::DOKUMEN_LPJ_WAJIB, null),
            ...$berkas,
        ]);

        return $usulan->fresh('dokumen');
    }

    private function pengingat(): PengingatDokumen
    {
        return app(PengingatDokumen::class);
    }

    // ── Tenggang ──

    public function test_pengingat_belum_dikirim_sebelum_tenggang_lewat(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 3]);
        $this->perjadinSelesai(1);

        $this->assertSame(0, $this->pengingat()->jalankan()['terkirim']);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    public function test_pengingat_dikirim_setelah_tenggang_terlampaui(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 3]);
        $usulan = $this->perjadinSelesai(4);

        $this->assertSame(1, $this->pengingat()->jalankan()['terkirim']);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelaksana->id,
            'id_usulan' => $usulan->id,
            'judul' => 'Berkas pertanggungjawaban belum lengkap',
        ]);
    }

    public function test_tenggang_yang_diperpanjang_menunda_pengingat(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 14]);
        $this->perjadinSelesai(4);

        $this->assertSame(0, $this->pengingat()->jalankan()['terkirim']);
    }

    public function test_berkas_lengkap_tidak_diingatkan(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 1]);
        $usulan = $this->perjadinSelesai(10);
        $this->lengkapiPertanggungjawaban($usulan);

        $this->assertSame(0, $this->pengingat()->jalankan()['terkirim']);
    }

    public function test_notifikasi_menyebut_berkas_yang_belum_diunggah(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 1]);
        $this->perjadinSelesai(5, ['sppd' => 'dokumen/sppd.pdf']);

        $this->pengingat()->jalankan();

        $pesan = Notifikasi::first()->pesan;

        $this->assertStringContainsString('Tiket Pergi', $pesan);
        $this->assertStringNotContainsString('SPPD', $pesan);
    }

    // ── Jeda ulang dan batas ──

    public function test_pengingat_tidak_dikirim_dua_kali_pada_hari_yang_sama(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 1, Pengaturan::PENGINGAT_ULANG => 7]);
        $this->perjadinSelesai(5);

        $this->assertSame(1, $this->pengingat()->jalankan()['terkirim']);
        $this->assertSame(0, $this->pengingat()->jalankan()['terkirim']);
        $this->assertDatabaseCount('notifikasi', 1);
    }

    public function test_pengingat_berikutnya_menunggu_jeda_yang_diatur(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 1, Pengaturan::PENGINGAT_ULANG => 7]);
        $usulan = $this->perjadinSelesai(5);

        $this->pengingat()->jalankan();

        $usulan->update(['pengingat_terakhir_at' => now()->subDays(8)]);

        $this->assertSame(1, $this->pengingat()->jalankan()['terkirim']);
        $this->assertDatabaseCount('notifikasi', 2);
    }

    public function test_pengingat_berhenti_setelah_mencapai_batas(): void
    {
        Pengaturan::simpan([
            Pengaturan::PENGINGAT_HARI => 1,
            Pengaturan::PENGINGAT_ULANG => 1,
            Pengaturan::PENGINGAT_MAKS => 2,
        ]);
        $usulan = $this->perjadinSelesai(5);

        for ($i = 0; $i < 4; $i++) {
            $this->pengingat()->jalankan();
            $usulan->update(['pengingat_terakhir_at' => now()->subDays(3 + $i)]);
        }

        $this->assertDatabaseCount('notifikasi', 2);
        $this->assertSame(2, $usulan->fresh()->pengingat_terkirim);
    }

    public function test_batas_nol_berarti_tanpa_batas(): void
    {
        Pengaturan::simpan([
            Pengaturan::PENGINGAT_HARI => 1,
            Pengaturan::PENGINGAT_ULANG => 1,
            Pengaturan::PENGINGAT_MAKS => 0,
        ]);
        $usulan = $this->perjadinSelesai(5);

        for ($i = 0; $i < 3; $i++) {
            $this->pengingat()->jalankan();
            $usulan->update(['pengingat_terakhir_at' => now()->subDays(3 + $i)]);
        }

        $this->assertDatabaseCount('notifikasi', 3);
    }

    public function test_sakelar_mati_menghentikan_seluruh_pengingat(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_AKTIF => '0', Pengaturan::PENGINGAT_HARI => 1]);
        $this->perjadinSelesai(10);

        $this->assertSame(0, $this->pengingat()->jalankan()['terkirim']);
        $this->assertDatabaseCount('notifikasi', 0);
    }

    // ── Pengaturan di Administrasi Sistem ──

    public function test_panel_pengaturan_tampil_bagi_super_administrator(): void
    {
        $this->actingAs($this->admin())
            ->get(route('administrasi'))
            ->assertOk()
            ->assertSee('Pengingat Kelengkapan Berkas')
            ->assertSee('Aktifkan pengingat otomatis')
            ->assertSee('perjadin belum lengkap');
    }

    public function test_admin_dapat_menyimpan_pengaturan(): void
    {
        $this->actingAs($this->admin())
            ->put(route('administrasi.pengaturan'), [
                'pengingat_aktif' => '1',
                'pengingat_hari' => 10,
                'pengingat_ulang' => 5,
                'pengingat_maksimal' => 3,
            ])
            ->assertSessionHas('success');

        $this->assertSame(10, Pengaturan::angka(Pengaturan::PENGINGAT_HARI));
        $this->assertSame(5, Pengaturan::angka(Pengaturan::PENGINGAT_ULANG));
        $this->assertSame(3, Pengaturan::angka(Pengaturan::PENGINGAT_MAKS));
        $this->assertTrue(Pengaturan::aktif(Pengaturan::PENGINGAT_AKTIF));
    }

    public function test_nilai_tersimpan_terbaca_kembali_pada_formulir(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('administrasi.pengaturan'), [
            'pengingat_aktif' => '1',
            'pengingat_hari' => 2,
            'pengingat_ulang' => 3,
            'pengingat_maksimal' => 5,
        ]);

        // Formulir membaca Pengaturan::semua(), bukan kolomnya satu per satu.
        $this->actingAs($admin)
            ->get(route('administrasi'))
            ->assertOk()
            ->assertViewHas('pengaturan', fn (array $isi) => $isi[Pengaturan::PENGINGAT_HARI] === '2'
                && $isi[Pengaturan::PENGINGAT_ULANG] === '3'
                && $isi[Pengaturan::PENGINGAT_MAKS] === '5');
    }

    public function test_pengaturan_yang_belum_pernah_diisi_memakai_nilai_bawaan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('administrasi'))
            ->assertOk()
            ->assertViewHas('pengaturan', fn (array $isi) => $isi === Pengaturan::BAWAAN);
    }

    public function test_tenggang_di_luar_batas_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->put(route('administrasi.pengaturan'), [
                'pengingat_hari' => 400,
                'pengingat_ulang' => 0,
                'pengingat_maksimal' => 99,
            ])
            ->assertSessionHasErrors(['pengingat_hari', 'pengingat_ulang', 'pengingat_maksimal']);
    }

    public function test_admin_dapat_menjalankan_pengingat_secara_manual(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 1]);
        $this->perjadinSelesai(5);

        $this->actingAs($this->admin())
            ->post(route('administrasi.pengingat'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('notifikasi', 1);
    }

    public function test_pengguna_biasa_tidak_dapat_mengubah_pengaturan(): void
    {
        $this->actingAs($this->pelaksana)
            ->put(route('administrasi.pengaturan'), [
                'pengingat_hari' => 1,
                'pengingat_ulang' => 1,
                'pengingat_maksimal' => 1,
            ])
            ->assertForbidden();
    }

    public function test_perintah_artisan_menjalankan_pengingat(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 1]);
        $this->perjadinSelesai(5);

        $this->artisan('pangi:pengingat-dokumen')
            ->expectsOutputToContain('Pengingat terkirim: 1')
            ->assertSuccessful();
    }

    // ── Batas laporan yang ditampilkan ke pengguna ──

    public function test_batas_laporan_jatuh_pada_h_plus_tenggang(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 3]);
        $usulan = $this->perjadinSelesai(1);

        $this->assertTrue(
            today()->addDays(2)->isSameDay($this->pengingat()->batasLaporan($usulan)),
            'Berakhir kemarin dengan tenggang 3 hari, batasnya dua hari lagi.'
        );
        $this->assertSame(2, $this->pengingat()->sisaHari($usulan));
    }

    public function test_sisa_hari_negatif_setelah_batas_terlewat(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 3]);
        $usulan = $this->perjadinSelesai(10);

        $this->assertSame(-7, $this->pengingat()->sisaHari($usulan));
    }

    /**
     * Batas yang ditampilkan harus persis hari pertama pengingat dikirim —
     * kalau berbeda, pengguna dituduh terlambat sebelum tenggangnya habis,
     * atau ditagih diam-diam setelah batas yang tertulis masih aman.
     */
    public function test_batas_yang_ditampilkan_sama_dengan_saat_pengingat_mulai_menagih(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 3]);

        $sehariSebelumBatas = $this->perjadinSelesai(2);
        $this->assertSame(1, $this->pengingat()->sisaHari($sehariSebelumBatas));
        $this->assertSame(0, $this->pengingat()->jalankan()['terkirim']);

        $tepatDiBatas = $this->perjadinSelesai(3);
        $this->assertSame(0, $this->pengingat()->sisaHari($tepatDiBatas));
        $this->assertTrue($this->pengingat()->kandidat()->contains('id', $tepatDiBatas->id));
    }

    public function test_tenggang_hari_mengikuti_pengaturan(): void
    {
        Pengaturan::simpan([Pengaturan::PENGINGAT_HARI => 7]);

        $this->assertSame(7, $this->pengingat()->tenggangHari());
    }
}
