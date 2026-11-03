<?php

namespace Database\Factories;

use App\Models\TimKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimKerja>
 */
class TimKerjaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'Tim '.fake()->unique()->words(2, true),
            'kewadiran' => fake()->randomElement(['Kewadiran I', 'Kewadiran II', 'Kewadiran III']),
            'urutan' => fake()->numberBetween(1, 50),
            'is_aktif' => true,
        ];
    }
}
