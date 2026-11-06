<?php

namespace Database\Factories;

use App\Models\Pengumuman;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengumuman>
 */
class PengumumanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'judul' => fake()->sentence(4),
            'isi' => fake()->paragraph(),
            'mulai_tayang' => today()->subDay(),
            'selesai_tayang' => null,
        ];
    }

    public function terjadwal(): static
    {
        return $this->state(['mulai_tayang' => today()->addDays(3)]);
    }

    public function berakhir(): static
    {
        return $this->state(['mulai_tayang' => today()->subDays(10), 'selesai_tayang' => today()->subDay()]);
    }
}
