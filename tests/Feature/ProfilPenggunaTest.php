<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengguna = User::factory()->create([
            'role' => User::ROLE_DOSEN_TENDIK,
            'password' => 'rahasia-lama',
        ]);
    }

    // ── Akses ──

    public function test_setiap_peran_dapat_membuka_panel_profilnya(): void
    {
        foreach (PeranPengguna::cases() as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran->value]))
                ->get(route('profil.index'))
                ->assertOk();
        }
    }

    public function test_tamu_tidak_dapat_membuka_panel_profil(): void
    {
        $this->get(route('profil.index'))->assertRedirect(route('login'));
    }

    // ── Ganti kata sandi sendiri ──

    public function test_pengguna_dapat_mengganti_kata_sandinya_sendiri(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.password'), [
                'password_lama' => 'rahasia-lama',
                'password' => 'rahasia-baru-2026',
                'password_confirmation' => 'rahasia-baru-2026',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('rahasia-baru-2026', $this->pengguna->fresh()->password));
    }

    public function test_kata_sandi_lama_yang_salah_ditolak(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.password'), [
                'password_lama' => 'tebakan-keliru',
                'password' => 'rahasia-baru-2026',
                'password_confirmation' => 'rahasia-baru-2026',
            ])
            ->assertSessionHasErrors('password_lama');

        $this->assertTrue(Hash::check('rahasia-lama', $this->pengguna->fresh()->password));
    }

    public function test_konfirmasi_kata_sandi_harus_cocok(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.password'), [
                'password_lama' => 'rahasia-lama',
                'password' => 'rahasia-baru-2026',
                'password_confirmation' => 'beda-sendiri',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_kata_sandi_baru_minimal_delapan_karakter(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.password'), [
                'password_lama' => 'rahasia-lama',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_penggantian_kata_sandi_tercatat_di_jejak_audit(): void
    {
        $this->actingAs($this->pengguna)->put(route('profil.password'), [
            'password_lama' => 'rahasia-lama',
            'password' => 'rahasia-baru-2026',
            'password_confirmation' => 'rahasia-baru-2026',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id_user' => $this->pengguna->id,
            'deskripsi' => "Pengguna \"{$this->pengguna->nama}\" mengganti kata sandinya sendiri.",
        ]);
    }

    // ── Reset oleh super administrator ──

    public function test_super_administrator_dapat_mereset_kata_sandi_pengguna_lain(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('administrasi.password', $this->pengguna), [
                'password' => 'reset-oleh-admin',
                'password_confirmation' => 'reset-oleh-admin',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('reset-oleh-admin', $this->pengguna->fresh()->password));
    }

    public function test_pengguna_biasa_tidak_dapat_mereset_kata_sandi_orang_lain(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('administrasi.password', User::factory()->create()), [
                'password' => 'coba-bajak-akun',
                'password_confirmation' => 'coba-bajak-akun',
            ])
            ->assertForbidden();
    }

    // ── Rekening bank ──

    public function test_pengguna_dapat_mendaftarkan_rekening_banknya(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.rekening'), [
                'nama_bank' => 'Bank SulutGo',
                'nomor_rekening' => '1234567890',
                'nama_rekening' => 'Rahmat Hidayat',
            ])
            ->assertSessionHas('success');

        $pengguna = $this->pengguna->fresh();

        $this->assertSame('Bank SulutGo', $pengguna->nama_bank);
        $this->assertSame('1234567890', $pengguna->nomor_rekening);
        $this->assertTrue($pengguna->punyaRekening());
    }

    public function test_nomor_rekening_menolak_karakter_selain_angka(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.rekening'), [
                'nama_bank' => 'Bank Mandiri',
                'nomor_rekening' => 'DROP TABLE users',
                'nama_rekening' => 'Rahmat Hidayat',
            ])
            ->assertSessionHasErrors('nomor_rekening');
    }

    public function test_seluruh_kolom_rekening_wajib_diisi(): void
    {
        $this->actingAs($this->pengguna)
            ->put(route('profil.rekening'), ['nama_bank' => 'Bank BRI'])
            ->assertSessionHasErrors(['nomor_rekening', 'nama_rekening']);
    }

    public function test_pengguna_tidak_dapat_mengubah_perannya_lewat_panel_profil(): void
    {
        $this->actingAs($this->pengguna)->put(route('profil.rekening'), [
            'nama_bank' => 'Bank BNI',
            'nomor_rekening' => '999888777',
            'nama_rekening' => 'Rahmat Hidayat',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->assertSame(User::ROLE_DOSEN_TENDIK, $this->pengguna->fresh()->role);
    }
}
