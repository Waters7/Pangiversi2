<?php

namespace Tests\Feature;

use App\Enums\KategoriBiaya;
use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\AuditLog;
use App\Models\DaftarRiil;
use App\Models\Keuangan;
use App\Models\Pengumuman;
use App\Models\Peran;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Menu Pengumuman dibaca seluruh pengguna dan dikelola tim keuangan serta
 * super administrator. Popup saat masuk memuat pengumuman yang tayang dan
 * dokumen pada Rincian Saya yang menunggu tanda tangan.
 */
class PengumumanTest extends TestCase
{
    use RefreshDatabase;

    private const KATA_SANDI = 'password';

    private User $pegawai;

    private User $timKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);
        $this->timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function isian(array $ganti = []): array
    {
        return [
            'judul' => 'Batas unggah bukti perjalanan',
            'isi' => 'Mohon unggah bukti sebelum akhir bulan.',
            'mulai_tayang' => today()->toDateString(),
            'selesai_tayang' => today()->addWeek()->toDateString(),
            ...$ganti,
        ];
    }

    /** Masuk lewat formulir, lalu membuka halaman tujuannya. */
    private function masuk(User $pengguna): TestResponse
    {
        $this->post('/login', ['nip' => $pengguna->nip, 'password' => self::KATA_SANDI])
            ->assertRedirect(route('dashboard'));

        return $this->get(route('dashboard'))->assertOk();
    }

    /** Rincian biaya pegawai yang sudah dikirim tim keuangan. */
    private function kirimRincianKePegawai(): Usulan
    {
        $usulan = Usulan::factory()->create([
            'id_user' => $this->pegawai->id,
            'status' => StatusUsulan::Disetujui->value,
            'lokasi' => 'Jakarta',
        ]);

        Keuangan::factory()->belumBayar()->create(['id_usulan' => $usulan->id])->rincianBiaya()->create([
            'kategori' => KategoriBiaya::UangHarian->value,
            'komponen' => 'Uang harian',
            'volume' => 2,
            'satuan' => 'OH',
            'harga_satuan' => 350_000,
            'jumlah' => 700_000,
        ]);

        $peserta = $usulan->peserta()->create([
            'id_user' => $this->pegawai->id,
            'nama' => $this->pegawai->nama,
            'peran' => 'ketua',
        ]);

        DaftarRiil::create(['id_usulan' => $usulan->id, 'id_peserta' => $peserta->id, 'total_riil' => 0])
            ->kirimKePegawai();

        return $usulan;
    }

    // ── Menu Pengumuman ──

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('pengumuman.index'))->assertRedirect(route('login'));
    }

    public function test_pegawai_hanya_membaca_yang_sedang_tayang(): void
    {
        Pengumuman::factory()->create(['judul' => 'Sedang tayang']);
        Pengumuman::factory()->terjadwal()->create(['judul' => 'Belum waktunya']);
        Pengumuman::factory()->berakhir()->create(['judul' => 'Sudah lewat']);

        $this->actingAs($this->pegawai)->get(route('pengumuman.index'))
            ->assertOk()
            ->assertSee('Sedang tayang')
            ->assertDontSee('Belum waktunya')
            ->assertDontSee('Sudah lewat')
            ->assertDontSee('Buat Pengumuman');
    }

    public function test_masa_tayang_mencakup_tanggal_mulai_dan_tanggal_selesai(): void
    {
        $tayang = Pengumuman::factory()->create(['mulai_tayang' => today(), 'selesai_tayang' => today()]);

        $this->assertTrue(Pengumuman::tayang()->whereKey($tayang)->exists());
        $this->assertSame(Pengumuman::TAYANG, $tayang->fresh()->status());
    }

    public function test_pengelola_melihat_semua_beserta_statusnya(): void
    {
        Pengumuman::factory()->create(['judul' => 'Sedang tayang']);
        Pengumuman::factory()->terjadwal()->create(['judul' => 'Belum waktunya']);
        Pengumuman::factory()->berakhir()->create(['judul' => 'Sudah lewat']);

        $this->actingAs($this->timKeuangan)->get(route('pengumuman.index'))
            ->assertOk()
            ->assertSee('Buat Pengumuman')
            ->assertSeeInOrder(['Belum waktunya', Pengumuman::TERJADWAL])
            ->assertSeeInOrder(['Sedang tayang', Pengumuman::TAYANG])
            ->assertSeeInOrder(['Sudah lewat', Pengumuman::BERAKHIR]);
    }

    /**
     * @return array<string, array{PeranPengguna}>
     */
    public static function peranPengelola(): array
    {
        return [
            'tim keuangan' => [PeranPengguna::TimKeuangan],
            'super administrator' => [PeranPengguna::SuperAdministrator],
        ];
    }

    #[DataProvider('peranPengelola')]
    public function test_pengelola_membuat_pengumuman(PeranPengguna $peran): void
    {
        $pengelola = User::factory()->create(['role' => $peran->value]);

        $this->actingAs($pengelola)
            ->post(route('pengumuman.store'), $this->isian())
            ->assertRedirect(route('pengumuman.index'));

        $pengumuman = Pengumuman::sole();
        $this->assertSame('Batas unggah bukti perjalanan', $pengumuman->judul);
        $this->assertSame($pengelola->id, $pengumuman->id_pembuat);
        $this->assertDatabaseHas('audit_logs', ['aksi' => AuditLog::AKSI_DIBUAT, 'objek' => 'pengumuman', 'id_user' => $pengelola->id]);
    }

    public function test_pengelola_mengubah_dan_menghapus_pengumuman(): void
    {
        $pengumuman = Pengumuman::factory()->create();

        $this->actingAs($this->timKeuangan)
            ->get(route('pengumuman.edit', $pengumuman))
            ->assertOk()
            ->assertSee($pengumuman->judul);

        $this->put(route('pengumuman.update', $pengumuman), $this->isian(['judul' => 'Judul baru']))
            ->assertRedirect(route('pengumuman.index'));
        $this->assertSame('Judul baru', $pengumuman->fresh()->judul);

        $this->delete(route('pengumuman.destroy', $pengumuman))->assertRedirect(route('pengumuman.index'));
        $this->assertModelMissing($pengumuman);
    }

    public function test_pegawai_tidak_dapat_mengelola_pengumuman(): void
    {
        $pengumuman = Pengumuman::factory()->create(['judul' => 'Judul asli']);

        $this->actingAs($this->pegawai);

        $this->get(route('pengumuman.create'))->assertForbidden();
        $this->post(route('pengumuman.store'), $this->isian())->assertForbidden();
        $this->get(route('pengumuman.edit', $pengumuman))->assertForbidden();
        $this->put(route('pengumuman.update', $pengumuman), $this->isian())->assertForbidden();
        $this->delete(route('pengumuman.destroy', $pengumuman))->assertForbidden();

        $this->assertSame('Judul asli', Pengumuman::sole()->judul);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function isianTidakSah(): array
    {
        return [
            'judul kosong' => ['judul', ['judul' => '']],
            'judul terlalu panjang' => ['judul', ['judul' => str_repeat('a', 151)]],
            'isi kosong' => ['isi', ['isi' => '']],
            'isi terlalu panjang' => ['isi', ['isi' => str_repeat('a', 2001)]],
            'mulai tayang kosong' => ['mulai_tayang', ['mulai_tayang' => '']],
            'mulai tayang bukan tanggal' => ['mulai_tayang', ['mulai_tayang' => 'besok']],
            'selesai sebelum mulai' => ['selesai_tayang', ['mulai_tayang' => '2026-11-10', 'selesai_tayang' => '2026-11-09']],
        ];
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    #[DataProvider('isianTidakSah')]
    public function test_isian_tidak_sah_ditolak(string $kolom, array $ganti): void
    {
        $this->actingAs($this->timKeuangan)
            ->from(route('pengumuman.create'))
            ->post(route('pengumuman.store'), $this->isian($ganti))
            ->assertSessionHasErrors($kolom);

        $this->assertDatabaseCount('pengumuman', 0);
    }

    public function test_tanggal_selesai_boleh_kosong(): void
    {
        $this->actingAs($this->timKeuangan)
            ->post(route('pengumuman.store'), $this->isian(['selesai_tayang' => null]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Pengumuman::tayang()->exists());
    }

    public function test_isi_pengumuman_tampil_sebagai_teks_biasa(): void
    {
        $bahaya = '<script>alert(1)</script>';
        Pengumuman::factory()->create(['judul' => $bahaya, 'isi' => $bahaya]);

        $this->masuk($this->pegawai)
            ->assertSee('data-popup-masuk', false)
            ->assertDontSee($bahaya, false);

        $this->get(route('pengumuman.index'))
            ->assertOk()
            ->assertDontSee($bahaya, false)
            ->assertSee($bahaya);
    }

    public function test_menu_pengumuman_tampil_bagi_semua_pengguna(): void
    {
        $this->actingAs($this->pegawai)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('pengumuman.index'), false);
    }

    // ── Popup saat masuk ──

    public function test_popup_memuat_pengumuman_tayang_dan_muncul_sekali_setiap_masuk(): void
    {
        Pengumuman::factory()->create(['judul' => 'Sedang tayang']);
        Pengumuman::factory()->terjadwal()->create(['judul' => 'Belum waktunya']);

        $this->masuk($this->pegawai)
            ->assertSee('data-popup-masuk', false)
            ->assertSee('Sedang tayang')
            ->assertDontSee('Belum waktunya')
            ->assertDontSee('data-popup-dokumen', false);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('data-popup-masuk', false);

        $this->post(route('logout'));

        $this->masuk($this->pegawai)->assertSee('data-popup-masuk', false);
    }

    public function test_popup_memberi_tahu_dokumen_masuk_ke_rincian_saya(): void
    {
        $usulan = $this->kirimRincianKePegawai();

        $this->masuk($this->pegawai)
            ->assertSee('data-popup-masuk', false)
            ->assertSee('1 dokumen menunggu tanda tangan Anda')
            ->assertSee($usulan->no_usulan)
            ->assertSee(route('rincian-saya.rincian-biaya', ['cari' => $usulan->no_usulan]), false)
            ->assertDontSee('data-popup-pengumuman', false);
    }

    public function test_dokumen_pegawai_lain_tidak_masuk_popup(): void
    {
        $this->kirimRincianKePegawai();

        $this->masuk($this->timKeuangan)->assertDontSee('data-popup-masuk', false);
    }

    public function test_popup_tidak_muncul_bila_tidak_ada_pemberitahuan(): void
    {
        Pengumuman::factory()->berakhir()->create();

        $this->masuk($this->pegawai)->assertDontSee('data-popup-masuk', false);
    }

    public function test_popup_tidak_muncul_tanpa_proses_masuk(): void
    {
        Pengumuman::factory()->create();

        $this->actingAs($this->pegawai)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-popup-masuk', false);
    }

    // ── Hak akses tersimpan ──

    public function test_migrasi_memberi_hak_kepada_tim_keuangan_yang_perannya_tersimpan(): void
    {
        Peran::sinkronBawaan();
        $timKeuangan = Peran::where('kode', PeranPengguna::TimKeuangan->value)->sole();
        $timSdm = Peran::where('kode', PeranPengguna::TimSdm->value)->sole();
        DB::table('hak_akses_peran')->where('kemampuan', Kemampuan::MengelolaPengumuman->value)->delete();

        $migrasi = require database_path('migrations/2026_11_06_061743_beri_tim_keuangan_hak_mengelola_pengumuman.php');
        $migrasi->up();

        $this->assertTrue($timKeuangan->fresh()->punya(Kemampuan::MengelolaPengumuman));
        $this->assertFalse($timSdm->fresh()->punya(Kemampuan::MengelolaPengumuman));
    }
}
