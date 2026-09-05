<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Terbitnya SPD dikabarkan kepada pelaksananya.
 *
 * Usulan perjadin baru boleh diajukan setelah SPD ada. Tanpa kabar ini,
 * pelaksana yang menunggu tidak punya cara lain selain masuk berkali-kali
 * memeriksa sendiri — dan berkasnya menganggur selama ia menunggu.
 */
class KabarSpdTerbitTest extends TestCase
{
    use RefreshDatabase;

    private User $pembuat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pembuat = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
    }

    /**
     * @param  list<array<string, mixed>>  $pelaksana
     * @return array<string, mixed>
     */
    private function formulir(array $pelaksana): array
    {
        return [
            'dikeluarkan_di' => 'Manado',
            'pelaksana' => $pelaksana,
            'maksud' => 'Rapat koordinasi program.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => today()->addDays(7)->toDateString(),
            'tanggal_kembali' => today()->addDays(9)->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(User $orang, string $nomor): array
    {
        return [
            'nomor_surat' => $nomor,
            'nama' => $orang->nama,
            'nip' => $orang->nip ?? '198001012010011001',
            'id_user' => $orang->id,
        ];
    }

    public function test_pelaksana_dikabari_saat_spd_terbit(): void
    {
        $pelaksana = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($this->pembuat)
            ->post('/spd', $this->formulir([$this->baris($pelaksana, '11')]))
            ->assertRedirect();

        $kabar = Notifikasi::where('id_user', $pelaksana->id)->first();

        $this->assertNotNull($kabar, 'Pelaksana tidak menerima kabar apa pun.');
        $this->assertSame('Surat Perjalanan Dinas terbit', $kabar->judul);
        $this->assertStringContainsString('Jakarta', $kabar->pesan);
        $this->assertStringContainsString('/spd/', $kabar->url);
    }

    /** Seluruh pelaksana dalam satu SPD rombongan ikut dikabari. */
    public function test_setiap_pelaksana_rombongan_dikabari(): void
    {
        $rombongan = User::factory()->count(3)->create(['role' => User::ROLE_DOSEN_TENDIK]);

        $this->actingAs($this->pembuat)
            ->post('/spd', $this->formulir(
                $rombongan->values()
                    ->map(fn (User $orang, int $i) => $this->baris($orang, (string) (20 + $i)))
                    ->all()
            ))
            ->assertRedirect();

        foreach ($rombongan as $orang) {
            $this->assertDatabaseHas('notifikasi', [
                'id_user' => $orang->id,
                'judul' => 'Surat Perjalanan Dinas terbit',
            ]);
        }
    }

    /** Yang menerbitkannya sendiri sudah tahu; ia tidak perlu dikabari. */
    public function test_pembuat_tidak_mengabari_dirinya_sendiri(): void
    {
        $this->actingAs($this->pembuat)
            ->post('/spd', $this->formulir([$this->baris($this->pembuat, '31')]))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifikasi', ['id_user' => $this->pembuat->id]);
    }

    /**
     * Pelaksana dari luar sistem dicatat namanya saja, tanpa akun. Tidak
     * adanya penerima tidak boleh menggagalkan penerbitan SPD-nya.
     */
    public function test_pelaksana_tanpa_akun_tidak_menggagalkan_penerbitan(): void
    {
        $formulir = $this->formulir([[
            'nomor_surat' => '41',
            'nama' => 'Narasumber Luar',
            'nip' => '-',
            'id_user' => null,
        ]]);

        $this->actingAs($this->pembuat)
            ->post('/spd', $formulir)
            ->assertRedirect();

        $this->assertDatabaseCount('notifikasi', 0);
        $this->assertDatabaseCount('surat_perjalanan_dinas', 1);
    }
}
