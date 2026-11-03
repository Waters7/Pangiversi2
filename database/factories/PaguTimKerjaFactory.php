<?php

namespace Database\Factories;

use App\Models\AkunPembiayaan;
use App\Models\PaguTimKerja;
use App\Models\TahunAnggaran;
use App\Models\TimKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaguTimKerja>
 */
class PaguTimKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_tahun_anggaran' => TahunAnggaran::factory(),
            'id_akun_pembiayaan' => fn () => AkunPembiayaan::create([
                'kode' => fake()->unique()->numerify('529###'),
                'nama' => 'Belanja Perjalanan '.fake()->word(),
            ])->id,
            'id_kategori_pembiayaan' => null,
            'id_tim_kerja' => TimKerja::factory(),
            'pagu' => fake()->numberBetween(5, 500) * 1_000_000,
        ];
    }
}
