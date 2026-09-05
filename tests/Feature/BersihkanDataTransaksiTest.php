<?php

namespace Tests\Feature;

use App\Models\DaftarRiil;
use App\Models\Dokumen;
use App\Models\KategoriPerjadin;
use App\Models\Keuangan;
use App\Models\KomponenBiaya;
use App\Models\LokasiTujuan;
use App\Models\Notifikasi;
use App\Models\PesertaUsulan;
use App\Models\RincianBiaya;
use App\Models\TahunAnggaran;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menjelang rilis, data uji coba dikosongkan tetapi akun pengguna dan data
 * master harus tetap utuh — itulah bekal awal sistem saat dipakai sungguhan.
 */
class BersihkanDataTransaksiTest extends TestCase
{
    use RefreshDatabase;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->usulan = Usulan::factory()->create([
            'id_user' => User::factory()->create()->id,
        ]);

        PesertaUsulan::factory()->create(['id_usulan' => $this->usulan->id]);
        Dokumen::create(['id_usulan' => $this->usulan->id, 'surat_tugas' => 'st.pdf']);

        $keuangan = Keuangan::factory()->create(['id_usulan' => $this->usulan->id]);
        RincianBiaya::factory()->create(['id_keuangan' => $keuangan->id]);
        DaftarRiil::factory()->create(['id_usulan' => $this->usulan->id]);

        Notifikasi::create([
            'id_user' => $this->usulan->id_user,
            'judul' => 'Uji coba',
            'pesan' => 'Data percobaan.',
        ]);
    }

    public function test_seluruh_data_transaksi_dikosongkan(): void
    {
        $this->artisan('pangi:bersihkan-transaksi', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('usulan', 0);
        $this->assertDatabaseCount('peserta_usulan', 0);
        $this->assertDatabaseCount('dokumen', 0);
        $this->assertDatabaseCount('keuangan', 0);
        $this->assertDatabaseCount('rincian_biayas', 0);
        $this->assertDatabaseCount('daftar_riil', 0);
        $this->assertDatabaseCount('notifikasi', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_akun_pengguna_dan_data_master_dipertahankan(): void
    {
        $pengguna = User::count();

        UnitKerja::factory()->create();
        KategoriPerjadin::factory()->create();
        KomponenBiaya::factory()->create();
        LokasiTujuan::factory()->create();
        TahunAnggaran::factory()->create();

        $this->artisan('pangi:bersihkan-transaksi', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame($pengguna, User::count());
        $this->assertSame(1, UnitKerja::count());
        $this->assertSame(1, KategoriPerjadin::count());
        $this->assertSame(1, KomponenBiaya::count());
        $this->assertSame(1, LokasiTujuan::count());
        $this->assertSame(1, TahunAnggaran::count());
    }

    public function test_penomoran_id_dimulai_ulang_dari_satu(): void
    {
        $this->artisan('pangi:bersihkan-transaksi', ['--force' => true])
            ->assertSuccessful();

        $baru = Usulan::factory()->create(['id_user' => User::first()->id]);

        $this->assertSame(1, $baru->id);
    }

    public function test_berkas_unggahan_hanya_dihapus_bila_diminta(): void
    {
        Storage::disk('public')->put('dokumen/surat-tugas/contoh.pdf', 'isi');
        Storage::disk('public')->put('foto-profil/xenna.jpg', 'isi');

        $this->artisan('pangi:bersihkan-transaksi', ['--force' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertExists('dokumen/surat-tugas/contoh.pdf');

        $this->artisan('pangi:bersihkan-transaksi', ['--force' => true, '--berkas' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('dokumen/surat-tugas/contoh.pdf');

        // Foto profil melekat pada akun, jadi tidak ikut terhapus.
        Storage::disk('public')->assertExists('foto-profil/xenna.jpg');
    }

    public function test_perintah_dibatalkan_bila_tidak_dikonfirmasi(): void
    {
        $this->artisan('pangi:bersihkan-transaksi')
            ->expectsConfirmation('Hapus 9 baris data transaksi? Tindakan ini tidak dapat dibatalkan.', 'no')
            ->assertFailed();

        $this->assertDatabaseHas('usulan', ['id' => $this->usulan->id]);
    }
}
