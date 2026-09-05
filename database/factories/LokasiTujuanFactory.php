<?php

namespace Database\Factories;

use App\Models\LokasiTujuan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LokasiTujuan>
 */
class LokasiTujuanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->city(),
            'provinsi' => fake()->state(),
            'jenis' => fake()->randomElement(['dalam_kota', 'luar_kota', 'luar_negeri']),
            'is_aktif' => true,
        ];
    }
}
