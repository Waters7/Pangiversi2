<?php

namespace Database\Factories;

use App\Models\PesertaUsulan;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PesertaUsulan>
 */
class PesertaUsulanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_usulan' => Usulan::factory(),
            'id_user' => null,
            'nama' => fake()->name(),
            'nip' => fake()->numerify('NIP-##########'),
            'jabatan' => fake()->jobTitle(),
            'peran' => 'anggota',
        ];
    }

    public function ketua(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'ketua',
        ]);
    }
}
