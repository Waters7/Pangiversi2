<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sapuan menyeluruh: tiap halaman dibuka oleh tiap peran.
 *
 * Pengujian lain memeriksa satu perilaku dengan teliti; yang ini memeriksa
 * satu hal saja pada seluruh halaman — bahwa halamannya tidak meledak.
 * Galat 500 pada satu menu yang jarang dibuka bisa lolos berbulan-bulan
 * tanpa sapuan seperti ini.
 *
 * Yang dianggap sah: 200 (terbuka), 302 (dialihkan), 403 (ditolak sesuai
 * kewenangan), 404 (data contohnya tidak ada). Yang tidak: 500.
 */
class SapuMenuTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Halaman yang butuh data tertentu diuji pengujian lain; di sini hanya
     * halaman yang berdiri tanpa parameter.
     *
     * @return list<string>
     */
    private function alamatTanpaParameter(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($rute) => in_array('GET', $rute->methods(), true))
            ->filter(fn ($rute) => in_array('web', $rute->middleware(), true))
            ->filter(fn ($rute) => ! str_contains($rute->uri(), '{'))
            ->reject(fn ($rute) => str_starts_with($rute->uri(), 'api/'))
            ->reject(fn ($rute) => in_array($rute->uri(), ['login', 'register', 'logout'], true))
            ->map(fn ($rute) => '/'.ltrim($rute->uri(), '/'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{0: PeranPengguna, 1: string}>
     */
    public static function peran(): array
    {
        return array_map(
            fn (PeranPengguna $peran) => [$peran, $peran->value],
            PeranPengguna::cases(),
        );
    }

    #[DataProvider('peran')]
    public function test_seluruh_halaman_terbuka_tanpa_galat(PeranPengguna $peran, string $nama): void
    {
        $pengguna = User::factory()->create(['role' => $peran->value]);
        $gagal = [];

        foreach ($this->alamatTanpaParameter() as $alamat) {
            $status = $this->actingAs($pengguna)->get($alamat)->getStatusCode();

            if (! in_array($status, [200, 302, 403, 404], true)) {
                $gagal[] = "{$alamat} → {$status}";
            }
        }

        $this->assertSame([], $gagal, "Peran {$nama} menemui galat pada: ".implode(', ', $gagal));
    }
}
