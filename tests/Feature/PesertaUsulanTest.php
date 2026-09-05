<?php

namespace Tests\Feature;

use App\Models\KomponenBiaya;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesertaUsulanTest extends TestCase
{
    use RefreshDatabase;

    private User $pengusul;

    private Usulan $usulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengusul = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $this->usulan = Usulan::factory()->create([
            'id_user' => $this->pengusul->id,
            'status' => 'draft',
        ]);
    }

    public function test_pengusul_dapat_menambah_peserta_manual(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'nama' => 'Budi Santoso',
                'nip' => 'NIP-1234567890',
                'jabatan' => 'Dosen',
                'peran' => 'anggota',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('peserta_usulan', [
            'id_usulan' => $this->usulan->id,
            'nama' => 'Budi Santoso',
            'peran' => 'anggota',
        ]);
    }

    public function test_peserta_yang_dipilih_dari_daftar_pegawai_mewarisi_identitasnya(): void
    {
        $pegawai = User::factory()->create([
            'nama' => 'Siti Aminah',
            'nip' => 'NIP-9999999999',
            'jabatan' => 'Bendahara Pengeluaran',
        ]);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'id_user' => $pegawai->id,
                'peran' => 'anggota',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('peserta_usulan', [
            'id_usulan' => $this->usulan->id,
            'id_user' => $pegawai->id,
            'nama' => 'Siti Aminah',
            'nip' => 'NIP-9999999999',
            'jabatan' => 'Bendahara Pengeluaran',
        ]);
    }

    public function test_pegawai_yang_sama_tidak_dapat_ditambahkan_dua_kali(): void
    {
        $pegawai = User::factory()->create();

        PesertaUsulan::factory()->create([
            'id_usulan' => $this->usulan->id,
            'id_user' => $pegawai->id,
        ]);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'id_user' => $pegawai->id,
                'peran' => 'anggota',
            ])
            ->assertSessionHasErrors('id_user');
    }

    public function test_nama_wajib_diisi_bila_peserta_bukan_pegawai_terdaftar(): void
    {
        $this->actingAs($this->pengusul)
            ->post(route('usulan.peserta.store', $this->usulan), ['peran' => 'anggota'])
            ->assertSessionHasErrors('nama');
    }

    public function test_hanya_ada_satu_ketua_tim_per_usulan(): void
    {
        $ketuaLama = PesertaUsulan::factory()->ketua()->create(['id_usulan' => $this->usulan->id]);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'nama' => 'Ketua Baru',
                'peran' => 'ketua',
            ])
            ->assertSessionHas('success');

        $this->assertSame('anggota', $ketuaLama->fresh()->peran);
        $this->assertSame(1, $this->usulan->peserta()->where('peran', 'ketua')->count());
    }

    public function test_pengguna_lain_tidak_dapat_menambah_peserta(): void
    {
        $orangLain = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($orangLain)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'nama' => 'Penyusup',
                'peran' => 'anggota',
            ])
            ->assertForbidden();
    }

    public function test_peserta_tidak_dapat_diubah_setelah_usulan_diajukan(): void
    {
        $this->usulan->update(['status' => 'diajukan']);

        $this->actingAs($this->pengusul)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'nama' => 'Terlambat',
                'peran' => 'anggota',
            ])
            ->assertForbidden();
    }

    public function test_administrator_ikut_terkunci_pada_usulan_yang_sudah_diajukan(): void
    {
        $this->usulan->update(['status' => 'diajukan']);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'nama' => 'Ditambahkan Admin',
                'peran' => 'anggota',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('peserta_usulan', ['nama' => 'Ditambahkan Admin']);
    }

    public function test_administrator_masih_dapat_mengubah_peserta_pada_usulan_draf(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post(route('usulan.peserta.store', $this->usulan), [
                'nama' => 'Ditambahkan Admin',
                'peran' => 'anggota',
            ])
            ->assertSessionHas('success');
    }

    public function test_peserta_terkunci_pada_usulan_yang_dibuatkan_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $usulan = Usulan::factory()->create([
            'id_user' => $pemilik->id,
            'id_pembuat' => $this->pengusul->id,
            'status' => 'draft',
        ]);

        $this->actingAs($pemilik)
            ->post(route('usulan.peserta.store', $usulan), [
                'nama' => 'Peserta Titipan',
                'peran' => 'anggota',
            ])
            ->assertForbidden();
    }

    public function test_halaman_detail_menjelaskan_alasan_peserta_terkunci(): void
    {
        $this->usulan->update(['status' => 'disetujui']);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Terkunci')
            ->assertSee('dasar penerbitan SPPD dan pembayaran')
            ->assertDontSee('+ Tambah Peserta');
    }

    public function test_pengusul_dapat_menghapus_peserta(): void
    {
        $peserta = PesertaUsulan::factory()->create(['id_usulan' => $this->usulan->id]);

        $this->actingAs($this->pengusul)
            ->delete(route('usulan.peserta.destroy', [$this->usulan, $peserta]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('peserta_usulan', ['id' => $peserta->id]);
    }

    public function test_halaman_detail_usulan_menampilkan_daftar_peserta(): void
    {
        PesertaUsulan::factory()->ketua()->create([
            'id_usulan' => $this->usulan->id,
            'nama' => 'Rina Wulandari',
        ]);

        $this->actingAs($this->pengusul)
            ->get(route('usulan.show', $this->usulan))
            ->assertOk()
            ->assertSee('Peserta Perjalanan')
            ->assertSee('Rina Wulandari')
            ->assertSee('Ketua Tim');
    }

    public function test_halaman_keuangan_menampilkan_standar_komponen_biaya(): void
    {
        // Standar biaya muncul pada form input, yang terbuka bagi tim keuangan.
        $timKeuangan = User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]);
        KomponenBiaya::factory()->create(['nama' => 'Uang Harian']);

        $this->actingAs($timKeuangan)
            ->get(route('keuangan.detail', $this->usulan))
            ->assertOk()
            ->assertSee('Uang Harian');
    }

    public function test_peserta_dari_usulan_lain_tidak_dapat_dihapus(): void
    {
        $usulanLain = Usulan::factory()->create(['id_user' => $this->pengusul->id, 'status' => 'draft']);
        $peserta = PesertaUsulan::factory()->create(['id_usulan' => $usulanLain->id]);

        $this->actingAs($this->pengusul)
            ->delete(route('usulan.peserta.destroy', [$this->usulan, $peserta]))
            ->assertNotFound();

        $this->assertDatabaseHas('peserta_usulan', ['id' => $peserta->id]);
    }
}
