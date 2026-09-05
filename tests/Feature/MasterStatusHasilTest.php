<?php

namespace Tests\Feature;

use App\Enums\StatusUsulan;
use App\Models\LaporanPerjadin;
use App\Models\StatusHasil;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status hasil laporan dikelola lewat Master Data, sehingga satuan kerja
 * dapat menambah pilihannya sendiri tanpa menunggu aplikasi ditempatkan ulang.
 */
class MasterStatusHasilTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function isian(array $ubahan = []): array
    {
        return array_merge([
            'nama' => 'Ditunda',
            'keterangan' => 'Kegiatan tertunda ke periode berikutnya.',
            'is_aktif' => '1',
        ], $ubahan);
    }

    // ── Halaman ──

    public function test_halaman_terbuka_bagi_super_administrator(): void
    {
        $this->actingAs($this->admin)
            ->get(route('master.status-hasil'))
            ->assertOk()
            ->assertSee('Status Hasil Laporan')
            ->assertSee('Selesai dikerjakan');
    }

    public function test_pengguna_biasa_tidak_dapat_membukanya(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]))
            ->get(route('master.status-hasil'))
            ->assertForbidden();
    }

    public function test_menu_master_data_menautkan_halaman_ini(): void
    {
        $this->actingAs($this->admin)
            ->get(route('master.lokasi'))
            ->assertOk()
            ->assertSee(route('master.status-hasil'), false)
            ->assertSee('Status Hasil');
    }

    // ── Tambah dan ubah ──

    public function test_status_baru_dapat_ditambahkan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.status-hasil.store'), $this->isian())
            ->assertRedirect(route('master.status-hasil'));

        $this->assertDatabaseHas('status_hasil', ['nama' => 'Ditunda', 'is_aktif' => true]);
    }

    public function test_status_baru_langsung_ditawarkan_pada_formulir_laporan(): void
    {
        $this->actingAs($this->admin)->post(route('master.status-hasil.store'), $this->isian());

        $pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $usulan = Usulan::factory()->create([
            'id_user' => $pelaksana->id,
            'status' => StatusUsulan::Disetujui->value,
        ]);

        $this->actingAs($pelaksana)
            ->get(route('dokumen.laporan.edit', $usulan))
            ->assertOk()
            ->assertSee('Ditunda');
    }

    public function test_nama_tidak_boleh_kembar(): void
    {
        $this->actingAs($this->admin)
            ->post(route('master.status-hasil.store'), $this->isian(['nama' => 'Selesai dikerjakan']))
            ->assertSessionHasErrors('nama');
    }

    public function test_urutan_diisi_sendiri_bila_dikosongkan(): void
    {
        $this->actingAs($this->admin)->post(route('master.status-hasil.store'), $this->isian());

        // Tiga status baku berurutan 1..3, jadi yang baru menempati urutan 4.
        $this->assertSame(4, StatusHasil::firstWhere('nama', 'Ditunda')->urutan);
    }

    public function test_status_dapat_diubah(): void
    {
        $status = StatusHasil::firstWhere('nama', 'Tidak selesai');

        $this->actingAs($this->admin)
            ->put(route('master.status-hasil.update', $status), $this->isian(['nama' => 'Belum tuntas']))
            ->assertRedirect(route('master.status-hasil'));

        $this->assertSame('Belum tuntas', $status->fresh()->nama);
    }

    public function test_status_nonaktif_berhenti_ditawarkan(): void
    {
        $status = StatusHasil::firstWhere('nama', 'Tidak selesai');

        $this->actingAs($this->admin)->put(route('master.status-hasil.update', $status), [
            'nama' => $status->nama,
        ]);

        $this->assertFalse($status->fresh()->is_aktif);
        $this->assertFalse(StatusHasil::pilihan()->contains('id', $status->id));
    }

    // ── Hapus ──

    public function test_status_yang_belum_dipakai_dapat_dihapus(): void
    {
        $status = StatusHasil::firstWhere('nama', 'Tidak selesai');

        $this->actingAs($this->admin)
            ->delete(route('master.status-hasil.destroy', $status))
            ->assertRedirect(route('master.status-hasil'));

        $this->assertDatabaseMissing('status_hasil', ['id' => $status->id]);
    }

    /**
     * Menghapus status yang terpakai membuat laporan lama kehilangan
     * keterangan hasilnya, jadi yang ditawarkan adalah menonaktifkannya.
     */
    public function test_status_yang_masih_dipakai_tidak_dapat_dihapus(): void
    {
        $status = StatusHasil::firstWhere('nama', 'Selesai dikerjakan');

        LaporanPerjadin::create([
            'id_usulan' => Usulan::factory()->create()->id,
            'id_status_hasil' => $status->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('master.status-hasil.destroy', $status))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('status_hasil', ['id' => $status->id]);
    }
}
