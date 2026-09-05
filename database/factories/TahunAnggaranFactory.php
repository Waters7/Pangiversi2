<?php

namespace Database\Factories;

use App\Models\TahunAnggaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAnggaran>
 */
class TahunAnggaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tahun' => fake()->unique()->numberBetween(2020, 2035),
            'pagu' => fake()->numberBetween(500_000_000, 5_000_000_000),
            'is_aktif' => false,
        ];
    }

    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_aktif' => true,
        ]);
    }
}
