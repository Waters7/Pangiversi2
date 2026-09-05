<?php

namespace Database\Factories;

use App\Models\KomponenBiaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KomponenBiaya>
 */
class KomponenBiayaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => ucfirst(fake()->unique()->words(2, true)),
            'satuan' => fake()->randomElement(['OH', 'OK', 'Paket', 'Hari']),
            'harga_satuan' => fake()->numberBetween(100_000, 2_000_000),
            'jenis' => fake()->randomElement(['sbm', 'at_cost']),
            'is_aktif' => true,
        ];
    }
}
