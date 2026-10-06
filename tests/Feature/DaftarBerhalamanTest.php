<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar yang dibagi per halaman wajib menampilkan navigasi halamannya.
 * Tanpa itu baris setelah halaman pertama tidak terjangkau sama sekali —
 * menu Keuangan menampilkan "17 data" tetapi hanya sepuluh barisnya.
 */
class DaftarBerhalamanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tujuh belas perjalanan berlaku, nomornya urut dari yang terlama.
     */
    private function perjalanan(int $jumlah = 17): void
    {
        foreach (range(1, $jumlah) as $urutan) {
            Usulan::factory()->create([
                'no_usulan' => sprintf('PJ-DIR-2026-10-%03d', $urutan),
                'status' => 'disetujui',
                'created_at' => now()->subMinutes($jumlah - $urutan),
            ]);
        }
    }

    public function test_menu_keuangan_menjangkau_seluruh_perjalanan_lewat_halaman_berikutnya(): void
    {
        $this->perjalanan();
        $timKeuangan = User::factory()->create(['role' => PeranPengguna::TimKeuangan->value]);

        $this->actingAs($timKeuangan)
            ->get(route('keuangan'))
            ->assertOk()
            ->assertSee('Menampilkan 1–10 dari 17 data')
            ->assertSee(route('keuangan', ['page' => 2]))
            ->assertSee('PJ-DIR-2026-10-017')
            ->assertDontSee('PJ-DIR-2026-10-007');

        $this->actingAs($timKeuangan)
            ->get(route('keuangan', ['page' => 2]))
            ->assertOk()
            ->assertSee('Menampilkan 11–17 dari 17 data')
            ->assertSee('PJ-DIR-2026-10-001')
            ->assertSee('PJ-DIR-2026-10-007');
    }

    public function test_daftar_persetujuan_juga_menampilkan_navigasi_halaman(): void
    {
        $this->perjalanan(12);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('persetujuan', ['tab' => 'semua']))
            ->assertOk()
            ->assertSee('Menampilkan 1–10 dari 12 data');
    }
}
