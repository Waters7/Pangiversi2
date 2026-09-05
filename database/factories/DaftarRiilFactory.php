<?php

namespace Database\Factories;

use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaftarRiil>
 */
class DaftarRiilFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $usulan = Usulan::factory();

        return [
            'id_usulan' => $usulan,
            'id_peserta' => PesertaUsulan::factory()->for($usulan, 'usulan'),
            'total_riil' => fake()->numberBetween(500_000, 5_000_000),
            'keterangan' => fake()->optional()->sentence(),
            'id_ppk' => null,
            'ditandatangani_at' => null,
        ];
    }

    public function ditandatangani(): static
    {
        return $this->state(fn (array $attributes) => [
            'id_ppk' => User::factory()->create(['role' => User::ROLE_PPK])->id,
            'ditandatangani_at' => now(),
        ]);
    }
}
