<?php

namespace Tests\Feature;

use App\Enums\JenisPerjadin;
use App\Enums\Kemampuan;
use App\Enums\PeranPengguna;
use App\Enums\StatusUsulan;
use App\Models\Peran;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Dokumen Perdin: tim keuangan, pimpinan, dan super administrator menelusuri
 * seluruh perjalanan lewat navigasinya; pegawai biasa mendapati perjalanannya
 * sendiri terurut per bulan.
 */
class NavigasiDokumenPerdinTest extends TestCase
{
    use RefreshDatabase;

    private function perjalanan(string $tanggal, array $atribut = []): Usulan
    {
        return Usulan::factory()->create($atribut + [
            'id_user' => User::factory()->create()->id,
            'status' => StatusUsulan::Disetujui->value,
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
        ]);
    }

    private function buka(array $saringan = []): TestResponse
    {
        return $this->actingAs(User::factory()->create(['role' => User::ROLE_TIM_KEUANGAN]))
            ->get(route('dokumen', $saringan))
            ->assertOk();
    }

    /** @return array<string, array{PeranPengguna}> */
    public static function peranPenelusur(): array
    {
        return [
            'tim keuangan' => [PeranPengguna::TimKeuangan],
            'pimpinan' => [PeranPengguna::Pimpinan],
            'super administrator' => [PeranPengguna::SuperAdministrator],
        ];
    }

    #[DataProvider('peranPenelusur')]
    public function test_peran_penelusur_melihat_seluruh_perjalanan_dengan_navigasi(PeranPengguna $peran): void
    {
        $usulan = $this->perjalanan('2026-08-10');

        $this->actingAs(User::factory()->create(['role' => $peran->value]))
            ->get(route('dokumen'))
            ->assertOk()
            ->assertSee($usulan->no_usulan)
            ->assertSee('data-navigasi-dokumen', false);

        $this->actingAs(User::factory()->create(['role' => $peran->value]))
            ->get(route('dokumen.show', $usulan))
            ->assertOk();
    }

    public function test_pegawai_biasa_hanya_mendapati_perjalanannya_terurut_per_bulan(): void
    {
        $pegawai = User::factory()->create(['role' => User::ROLE_DOSEN_TENDIK]);
        $maret = $this->perjalanan('2026-03-10', ['id_user' => $pegawai->id]);
        $agustus = $this->perjalanan('2026-08-10', ['id_user' => $pegawai->id]);
        $orangLain = $this->perjalanan('2026-08-11');

        $this->actingAs($pegawai)
            ->get(route('dokumen', ['bulan' => 3, 'jalur' => JenisPerjadin::DalamKota->value]))
            ->assertOk()
            ->assertSeeInOrder(['Agustus 2026', $agustus->no_usulan, 'Maret 2026', $maret->no_usulan])
            ->assertDontSee($orangLain->no_usulan)
            ->assertDontSee('data-navigasi-dokumen', false);
    }

    public function test_saringan_periode_dan_jalur_mempersempit_daftar(): void
    {
        $agustus = $this->perjalanan('2026-08-10', ['jenis_perjadin' => JenisPerjadin::LuarKota->value]);
        $maret = $this->perjalanan('2026-03-10', ['jenis_perjadin' => JenisPerjadin::DalamKota->value]);
        $tahunLalu = $this->perjalanan('2025-08-10', ['jenis_perjadin' => JenisPerjadin::LuarKota->value]);

        $this->buka(['tahun' => 2026, 'bulan' => 8])
            ->assertSee($agustus->no_usulan)
            ->assertDontSee($maret->no_usulan)
            ->assertDontSee($tahunLalu->no_usulan);

        $this->buka(['jalur' => JenisPerjadin::DalamKota->value])
            ->assertSee($maret->no_usulan)
            ->assertDontSee($agustus->no_usulan);
    }

    public function test_saringan_unit_kerja_dan_kelengkapan(): void
    {
        $unit = UnitKerja::factory()->create();
        $diUnit = $this->perjalanan('2026-08-10', ['id_user' => User::factory()->create(['id_unit' => $unit->id])->id]);
        $lengkap = $this->lengkapiPertanggungjawaban($this->perjalanan('2026-08-11', [
            'id_user' => User::factory()->create(['id_unit' => UnitKerja::factory()->create()->id])->id,
            'jenis_perjadin' => JenisPerjadin::DalamKota->value,
        ]));

        $this->buka(['unit' => $unit->id])->assertSee($diUnit->no_usulan)->assertDontSee($lengkap->no_usulan);
        $this->buka(['kelengkapan' => 'belum'])->assertSee($diUnit->no_usulan);
        $this->buka(['kelengkapan' => 'lengkap'])->assertDontSee($diUnit->no_usulan);
    }

    public function test_seluruh_perjalanan_terbagi_per_halaman(): void
    {
        $tertua = $this->perjalanan('2024-01-01');

        foreach (range(1, 25) as $hari) {
            $this->perjalanan('2026-08-'.str_pad((string) ($hari % 28 + 1), 2, '0', STR_PAD_LEFT));
        }

        $this->buka()->assertDontSee($tertua->no_usulan)->assertSee('dari 26 data');
        $this->buka(['page' => 2])->assertSee($tertua->no_usulan);
    }

    /** Peran yang hak aksesnya sudah tersimpan tidak memakai bawaan enum. */
    public function test_migrasi_memberi_hak_kepada_peran_yang_sudah_tersimpan(): void
    {
        $peran = Peran::create(['kode' => PeranPengguna::TimKeuangan->value, 'nama' => 'Tim Keuangan']);
        $peran->hakAkses()->create(['kemampuan' => Kemampuan::MelihatKeuangan->value]);

        $this->assertFalse(User::factory()->create(['role' => PeranPengguna::TimKeuangan->value])->punyaKemampuan(Kemampuan::MelihatSemuaDokumen));

        (require database_path('migrations/2026_11_05_013545_beri_tim_keuangan_dan_pimpinan_lihat_semua_dokumen.php'))->up();

        $this->assertDatabaseHas('hak_akses_peran', ['id_peran' => $peran->id, 'kemampuan' => Kemampuan::MelihatSemuaDokumen->value]);
    }
}
