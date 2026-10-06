<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nomor handphone pegawai dapat diisi dan disunting dari Administrasi
 * Sistem → Pengguna — dipakai tim keuangan menagih berkas lewat WhatsApp.
 */
class KelolaPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
    }

    /**
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    private function isian(User $pegawai, array $ubahan = []): array
    {
        return array_merge([
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'email' => $pegawai->email,
            'role' => $pegawai->role,
        ], $ubahan);
    }

    public function test_menyunting_pengguna_menyimpan_nomor_handphone(): void
    {
        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'no_hp' => null]);

        $this->actingAs($this->admin)
            ->put(route('administrasi.update', $pegawai), $this->isian($pegawai, ['no_hp' => '0812-3456-7890']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('administrasi'));

        $this->assertSame('0812-3456-7890', $pegawai->fresh()->no_hp);
        $this->assertSame('6281234567890', $pegawai->fresh()->nomor_whatsapp);
    }

    public function test_formulir_edit_membawa_nomor_handphone_tersimpan(): void
    {
        User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'no_hp' => '081299990000']);

        $this->actingAs($this->admin)
            ->get(route('administrasi'))
            ->assertOk()
            ->assertSee('name="no_hp"', false)
            ->assertSee('081299990000');
    }

    public function test_nomor_handphone_berisi_huruf_ditolak(): void
    {
        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK, 'no_hp' => '081200001111']);

        $this->actingAs($this->admin)
            ->put(route('administrasi.update', $pegawai), $this->isian($pegawai, ['no_hp' => 'nol delapan']))
            ->assertSessionHasErrors(['no_hp' => 'Nomor handphone hanya boleh berisi angka, spasi, tanda plus, atau tanda hubung.']);

        $this->assertSame('081200001111', $pegawai->fresh()->no_hp);
    }

    public function test_pengguna_baru_dapat_langsung_diberi_nomor_handphone(): void
    {
        $this->actingAs($this->admin)
            ->post(route('administrasi.store'), [
                'nama' => 'Pegawai Baru',
                'nip' => '199001012020121001',
                'role' => User::ROLE_DOSEN_TENDIK,
                'no_hp' => '+62 812 0000 2222',
                'password' => 'sandi-awal-123',
                'password_confirmation' => 'sandi-awal-123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('+62 812 0000 2222', User::firstWhere('nip', '199001012020121001')->no_hp);
    }
}
