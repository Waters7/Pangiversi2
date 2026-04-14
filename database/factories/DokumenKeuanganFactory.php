<?php

namespace Database\Factories;

use App\Models\DokumenKeuangan;
use App\Models\Keuangan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DokumenKeuangan>
 */
class DokumenKeuanganFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_uang_muka' => fake()->imageUrl(),
            'transfer_sisa' => fake()->imageUrl(),
            'id_keuangan' => function () {
                return Keuangan::inRandomOrder()->first()->id;
            },
        ];
    }
}
