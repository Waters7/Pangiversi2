<?php

namespace Tests\Feature;

use App\Models\ObrolanBantuan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Saluran bantuan: pengguna melaporkan kendala, administrator menjawab.
 *
 * Bentuknya percakapan supaya tanya jawabnya tersimpan utuh, dan tiap pihak
 * hanya melihat yang memang haknya: pelapor melihat obrolannya sendiri,
 * administrator melihat seluruhnya.
 */
class BantuanPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $pelapor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pelapor = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function laporkan(array $isian = []): ObrolanBantuan
    {
        $this->actingAs($this->pelapor)->post(route('bantuan.store'), array_merge([
            'judul' => 'Tombol unggah SPPD tidak dapat ditekan',
            'isi' => 'Saat membuka menu Dokumen, tombol unggah SPPD tidak memberi tanggapan apa pun.',
        ], $isian));

        return ObrolanBantuan::latest('id')->firstOrFail();
    }

    // ── Melaporkan kendala ──

    public function test_pengguna_melaporkan_kendala(): void
    {
        $obrolan = $this->laporkan();

        $this->assertSame($this->pelapor->id, $obrolan->id_pelapor);
        $this->assertSame(ObrolanBantuan::STATUS_TERBUKA, $obrolan->status);

        // Laporannya sendiri menjadi pesan pertama pada obrolan.
        $this->assertCount(1, $obrolan->pesan);
        $this->assertSame($this->pelapor->id, $obrolan->pesan->first()->id_pengirim);
    }

    public function test_laporan_memberitahu_administrator(): void
    {
        $obrolan = $this->laporkan();

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->admin->id,
            'judul' => 'Laporan kendala pengguna',
        ]);

        $this->assertNotNull($obrolan->judul);
    }

    public function test_laporan_dapat_menyebut_perjalanan_yang_dipersoalkan(): void
    {
        $usulan = Usulan::factory()->create(['id_user' => $this->pelapor->id]);

        $obrolan = $this->laporkan(['id_usulan' => $usulan->id]);

        $this->assertSame($usulan->id, $obrolan->id_usulan);
    }

    public function test_laporan_dapat_dilampiri_tangkapan_layar(): void
    {
        $obrolan = $this->laporkan([
            'lampiran' => UploadedFile::fake()->create('layar.png', 40, 'image/png'),
        ]);

        $this->assertNotNull($obrolan->pesan->first()->lampiran, 'Lampiran tidak tersimpan.');
    }

    public function test_uraian_kendala_wajib_cukup_rinci(): void
    {
        $this->actingAs($this->pelapor)
            ->post(route('bantuan.store'), ['judul' => 'Err', 'isi' => 'rusak'])
            ->assertSessionHasErrors(['judul', 'isi']);
    }

    // ── Tanya jawab ──

    public function test_balasan_administrator_menggeser_status_dan_memberitahu_pelapor(): void
    {
        $obrolan = $this->laporkan();

        $this->actingAs($this->admin)
            ->post(route('bantuan.balas', $obrolan), ['isi' => 'Sudah kami perbaiki, mohon dicoba lagi.'])
            ->assertSessionHas('success');

        $this->assertSame(ObrolanBantuan::STATUS_DIJAWAB, $obrolan->fresh()->status);

        $this->assertDatabaseHas('notifikasi', [
            'id_user' => $this->pelapor->id,
            'judul' => 'Balasan atas laporan kendala Anda',
        ]);
    }

    public function test_balasan_pelapor_mengembalikan_ke_antrian(): void
    {
        $obrolan = $this->laporkan();

        $this->actingAs($this->admin)->post(route('bantuan.balas', $obrolan), ['isi' => 'Sudah kami perbaiki.']);
        $this->actingAs($this->pelapor)->post(route('bantuan.balas', $obrolan), ['isi' => 'Masih belum bisa, Pak.']);

        $this->assertSame(ObrolanBantuan::STATUS_TERBUKA, $obrolan->fresh()->status);
    }

    public function test_obrolan_selesai_terbuka_lagi_bila_pelapor_membalas(): void
    {
        $obrolan = $this->laporkan();

        $this->actingAs($this->admin)
            ->put(route('bantuan.selesai', $obrolan))
            ->assertSessionHas('success');

        $this->assertTrue($obrolan->fresh()->sudahSelesai());

        $this->actingAs($this->pelapor)->post(route('bantuan.balas', $obrolan), ['isi' => 'Kendalanya muncul lagi hari ini.']);

        $segar = $obrolan->fresh();
        $this->assertFalse($segar->sudahSelesai());
        $this->assertNull($segar->diselesaikan_at);
    }

    public function test_pesan_lawan_bicara_ditandai_terbaca_saat_dibuka(): void
    {
        $obrolan = $this->laporkan();
        $this->actingAs($this->admin)->post(route('bantuan.balas', $obrolan), ['isi' => 'Sudah kami tindak lanjuti.']);

        $this->assertSame(1, $obrolan->fresh()->belumDibaca($this->pelapor));

        $this->actingAs($this->pelapor)->get(route('bantuan.show', $obrolan))->assertOk();

        $this->assertSame(0, $obrolan->fresh()->belumDibaca($this->pelapor));
    }

    // ── Tombol mengambang ──

    public function test_tombol_bantuan_tampil_pada_halaman_lain(): void
    {
        $this->actingAs($this->pelapor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Laporkan kendala ke administrator')
            ->assertSee(route('bantuan.index'), false);
    }

    /**
     * Di halaman bantuan itu sendiri tombolnya tidak berguna, jadi ia tidak
     * ikut menutupi isi halaman.
     */
    public function test_tombol_disembunyikan_pada_halaman_bantuan(): void
    {
        $this->actingAs($this->pelapor)
            ->get(route('bantuan.index'))
            ->assertOk()
            ->assertDontSee('Laporkan kendala ke administrator');
    }

    public function test_tombol_menghitung_jawaban_yang_menunggu_pelapor(): void
    {
        $obrolan = $this->laporkan();

        // Belum dijawab: belum ada yang perlu dilihat pelapor.
        $this->actingAs($this->pelapor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('ring-2 ring-slate-100', false);

        $this->actingAs($this->admin)->post(route('bantuan.balas', $obrolan), ['isi' => 'Sudah kami perbaiki.']);

        $this->actingAs($this->pelapor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ring-2 ring-slate-100', false);
    }

    public function test_tombol_administrator_menghitung_laporan_yang_belum_dijawab(): void
    {
        $this->laporkan();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Laporan kendala pengguna')
            ->assertSee('ring-2 ring-slate-100', false);
    }
    // ── Batas pandangan ──

    public function test_pelapor_hanya_melihat_obrolannya_sendiri(): void
    {
        $milikOrangLain = $this->laporkan();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('bantuan.show', $milikOrangLain))
            ->assertForbidden();
    }

    public function test_daftar_pelapor_tidak_memuat_obrolan_orang_lain(): void
    {
        $this->laporkan();

        $halaman = $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('bantuan.index'))
            ->assertOk();

        $this->assertSame(0, $halaman->viewData('obrolan')->total());
    }

    public function test_administrator_melihat_seluruh_obrolan(): void
    {
        $this->laporkan();

        $halaman = $this->actingAs($this->admin)->get(route('bantuan.index'))->assertOk();

        $this->assertSame(1, $halaman->viewData('obrolan')->total());
        $halaman->assertSee('Laporan Kendala Pengguna');
    }
}
