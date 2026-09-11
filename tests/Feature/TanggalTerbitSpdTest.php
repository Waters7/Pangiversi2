<?php

namespace Tests\Feature;

use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Models\SuratPerjalananDinas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tanggal terbit SPD hanya boleh disesuaikan pimpinan dan administrator.
 *
 * Bagi peran lain tanggalnya mengikuti tanggal pembuatan, sebab ia menyatakan
 * kapan surat benar-benar terbit. Yang berwenang boleh menyesuaikannya ketika
 * nomor surat sudah tercatat di buku agenda pada tanggal yang berbeda.
 */
class TanggalTerbitSpdTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>|null  $pelaksana
     * @return array<string, mixed>
     */
    private function formulir(User $orang, array $ganti = [], ?array $pelaksana = null): array
    {
        return $ganti + [
            'dikeluarkan_di' => 'Manado',
            'pelaksana' => $pelaksana ?? [[
                'nomor_surat' => '815',
                'nama' => $orang->nama,
                'nip' => $orang->nip ?? '198001012010011001',
                'id_user' => $orang->id,
            ]],
            'maksud' => 'Rapat koordinasi teknis.',
            'alat_angkut' => 'Angkutan Udara',
            'tempat_berangkat' => 'Manado',
            'tempat_tujuan' => 'Jakarta',
            'tanggal_berangkat' => today()->addDays(7)->toDateString(),
            'tanggal_kembali' => today()->addDays(9)->toDateString(),
        ];
    }

    // ── Siapa yang berwenang ──

    /**
     * @return list<array{0: PeranPengguna, 1: bool}>
     */
    public static function peran(): array
    {
        return array_map(
            fn (PeranPengguna $p) => [
                $p,
                in_array($p, [PeranPengguna::Pimpinan, PeranPengguna::SuperAdministrator], true),
            ],
            PeranPengguna::cases(),
        );
    }

    #[DataProvider('peran')]
    public function test_kewenangan_melekat_pada_pimpinan_dan_administrator_saja(
        PeranPengguna $peran,
        bool $berwenang,
    ): void {
        $this->assertSame(
            $berwenang,
            $peran->punya(Kemampuan::MengubahTanggalSpd),
            "Peran {$peran->value} keliru kewenangannya atas tanggal terbit SPD.",
        );
    }

    // ── Saat menerbitkan ──

    public function test_pimpinan_dapat_menetapkan_tanggal_terbit_saat_membuat(): void
    {
        $pimpinan = User::factory()->create(['role' => PeranPengguna::Pimpinan->value]);
        $agenda = today()->subDays(5);

        $this->actingAs($pimpinan)
            ->post('/spd', $this->formulir($pimpinan, ['tanggal_surat' => $agenda->toDateString()]))
            ->assertRedirect();

        $this->assertTrue(SuratPerjalananDinas::sole()->tanggal_surat->isSameDay($agenda));
    }

    public function test_peran_lain_selalu_mendapat_tanggal_hari_ini(): void
    {
        $pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        // Sengaja mengirim tanggal lain; kirimannya harus diabaikan.
        $this->actingAs($pelaksana)
            ->post('/spd', $this->formulir($pelaksana, [
                'tanggal_surat' => today()->subYear()->toDateString(),
            ]))
            ->assertRedirect();

        $this->assertTrue(SuratPerjalananDinas::sole()->tanggal_surat->isToday());
    }

    // ── Saat menyunting ──

    public function test_pimpinan_dapat_menyesuaikan_tanggal_terbit_yang_sudah_ada(): void
    {
        $pimpinan = User::factory()->create(['role' => PeranPengguna::Pimpinan->value]);
        $spd = $this->terbitkanSpd($pimpinan);

        $agenda = today()->subDays(12);

        $this->actingAs($pimpinan)
            ->put(route('spd.update', $spd), $this->formulir($pimpinan, [
                'tanggal_surat' => $agenda->toDateString(),
            ], [[
                'nomor_surat' => '815',
                'nama' => $pimpinan->nama,
                'nip' => $pimpinan->nip ?? '198001012010011001',
                'id_user' => $pimpinan->id,
            ]]))
            ->assertRedirect();

        $this->assertTrue($spd->fresh()->tanggal_surat->isSameDay($agenda));
    }

    public function test_pelaksana_tidak_dapat_menggeser_tanggal_terbit(): void
    {
        $pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);
        $spd = $this->terbitkanSpd($pelaksana);
        $semula = $spd->tanggal_surat;

        $this->actingAs($pelaksana)
            ->put(route('spd.update', $spd), $this->formulir($pelaksana, [
                'tanggal_surat' => today()->subYear()->toDateString(),
            ], [[
                'nomor_surat' => '900',
                'nama' => $pelaksana->nama,
                'nip' => $pelaksana->nip ?? '198001012010011001',
                'id_user' => $pelaksana->id,
            ]]))
            ->assertRedirect();

        $this->assertTrue(
            $spd->fresh()->tanggal_surat->isSameDay($semula),
            'Tanggal terbit bergeser padahal pengirimnya tidak berwenang.',
        );
    }

    /** Yang berwenang tetap wajib mengisinya — bukan boleh dikosongkan. */
    public function test_tanggal_wajib_diisi_bagi_yang_berwenang(): void
    {
        $pimpinan = User::factory()->create(['role' => PeranPengguna::Pimpinan->value]);

        $formulir = $this->formulir($pimpinan);
        unset($formulir['tanggal_surat']);

        $this->actingAs($pimpinan)
            ->post('/spd', $formulir)
            ->assertSessionHasErrors('tanggal_surat');
    }

    // ── Formulir ──

    public function test_formulir_menampilkan_isian_tanggal_bagi_yang_berwenang(): void
    {
        $pimpinan = User::factory()->create(['role' => PeranPengguna::Pimpinan->value]);

        $this->actingAs($pimpinan)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertSee('name="tanggal_surat"', escape: false)
            ->assertSee('Dapat disesuaikan dengan buku agenda');
    }

    public function test_formulir_mengunci_tanggal_bagi_peran_lain(): void
    {
        $pelaksana = User::factory()->create(['role' => PeranPengguna::DosenTendik->value]);

        $this->actingAs($pelaksana)
            ->get(route('spd.create'))
            ->assertOk()
            ->assertDontSee('name="tanggal_surat"', escape: false)
            ->assertSee('Mengikuti tanggal pembuatan SPD.');
    }

    /** Tanggal pilihan itulah yang tercetak pada dokumennya. */
    public function test_tanggal_pilihan_ikut_tercetak(): void
    {
        $pimpinan = User::factory()->create(['role' => PeranPengguna::Pimpinan->value]);
        $spd = $this->terbitkanSpd($pimpinan);

        $spd->update(['tanggal_surat' => today()->subDays(20)]);

        $this->actingAs($pimpinan)
            ->get(route('spd.cetak', $spd))
            ->assertOk();

        $this->assertTrue($spd->fresh()->tanggal_surat->isSameDay(today()->subDays(20)));
    }
}
