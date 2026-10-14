<?php

namespace Database\Factories;

use App\Models\AkunPembiayaan;
use App\Models\PaguAkun;
use App\Models\TahunAnggaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaguAkun>
 */
class PaguAkunFactory extends Factory
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
            'pagu' => fake()->numberBetween(100, 2_000) * 1_000_000,
        ];
    }
}
