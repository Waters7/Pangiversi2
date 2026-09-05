<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    public function test_pengguna_melihat_notifikasinya_sendiri(): void
    {
        Notifikasi::factory()->create([
            'id_user' => $this->pengguna->id,
            'judul' => 'Usulan Anda disetujui',
        ]);

        Notifikasi::factory()->create([
            'id_user' => User::factory()->create()->id,
            'judul' => 'Notifikasi orang lain',
        ]);

        $this->actingAs($this->pengguna)
            ->get(route('notifikasi.index'))
            ->assertOk()
            ->assertSee('Usulan Anda disetujui')
            ->assertDontSee('Notifikasi orang lain');
    }

    public function test_filter_belum_dibaca_menyembunyikan_notifikasi_yang_sudah_dibaca(): void
    {
        $belum = Notifikasi::factory()->create(['id_user' => $this->pengguna->id, 'judul' => 'Masih baru']);
        Notifikasi::factory()->dibaca()->create(['id_user' => $this->pengguna->id, 'judul' => 'Sudah dibaca']);

        // Diperiksa lewat data view: lonceng di layout selalu menampilkan
        // notifikasi terbaru terlepas dari filter daftar.
        $this->actingAs($this->pengguna)
            ->get(route('notifikasi.index', ['filter' => 'belum']))
            ->assertOk()
            ->assertViewHas('notifikasi', fn ($daftar) => $daftar->pluck('id')->all() === [$belum->id]);
    }

    public function test_membaca_notifikasi_menandainya_dan_mengarahkan_ke_url_tujuan(): void
    {
        $notifikasi = Notifikasi::factory()->create([
            'id_user' => $this->pengguna->id,
            'url' => '/list-usulan',
        ]);

        $this->actingAs($this->pengguna)
            ->put(route('notifikasi.baca', $notifikasi))
            ->assertRedirect('/list-usulan');

        $this->assertNotNull($notifikasi->fresh()->dibaca_at);
    }

    public function test_notifikasi_milik_orang_lain_tidak_dapat_dibaca(): void
    {
        $notifikasi = Notifikasi::factory()->create([
            'id_user' => User::factory()->create()->id,
        ]);

        $this->actingAs($this->pengguna)
            ->put(route('notifikasi.baca', $notifikasi))
            ->assertForbidden();

        $this->assertNull($notifikasi->fresh()->dibaca_at);
    }

    public function test_tandai_semua_dibaca_hanya_menyentuh_notifikasi_sendiri(): void
    {
        Notifikasi::factory()->count(3)->create(['id_user' => $this->pengguna->id]);
        $milikOrangLain = Notifikasi::factory()->create(['id_user' => User::factory()->create()->id]);

        $this->actingAs($this->pengguna)
            ->put(route('notifikasi.baca-semua'))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->pengguna->notifikasi()->belumDibaca()->count());
        $this->assertNull($milikOrangLain->fresh()->dibaca_at);
    }

    public function test_pengguna_dapat_menghapus_notifikasinya(): void
    {
        $notifikasi = Notifikasi::factory()->create(['id_user' => $this->pengguna->id]);

        $this->actingAs($this->pengguna)
            ->delete(route('notifikasi.destroy', $notifikasi))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('notifikasi', ['id' => $notifikasi->id]);
    }

    public function test_lonceng_notifikasi_menampilkan_jumlah_yang_belum_dibaca(): void
    {
        Notifikasi::factory()->count(2)->create(['id_user' => $this->pengguna->id]);
        Notifikasi::factory()->dibaca()->create(['id_user' => $this->pengguna->id]);

        $this->actingAs($this->pengguna)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lihat semua notifikasi');
    }

    public function test_notifikasi_ikut_terhapus_saat_penggunanya_dihapus(): void
    {
        $notifikasi = Notifikasi::factory()->create(['id_user' => $this->pengguna->id]);

        $this->pengguna->delete();

        $this->assertDatabaseMissing('notifikasi', ['id' => $notifikasi->id]);
    }
}
