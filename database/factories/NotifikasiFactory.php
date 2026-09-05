<?php

namespace Database\Factories;

use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notifikasi>
 */
class NotifikasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_user' => User::factory(),
            'id_usulan' => null,
            'judul' => fake()->sentence(3),
            'pesan' => fake()->sentence(),
            'tipe' => Notifikasi::TIPE_INFO,
            'url' => null,
            'dibaca_at' => null,
        ];
    }

    public function dibaca(): static
    {
        return $this->state(fn (array $attributes) => [
            'dibaca_at' => now(),
        ]);
    }
}
