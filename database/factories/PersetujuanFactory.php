<?php

namespace Database\Factories;

use App\Enums\LevelPersetujuan;
use App\Models\Persetujuan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persetujuan>
 */
class PersetujuanFactory extends Factory
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
            'id_approver' => User::factory(),
            'level' => LevelPersetujuan::Ppk,
            'peran' => 'ppk',
            'keputusan' => Persetujuan::KEPUTUSAN_SETUJU,
            'catatan' => null,
            'waktu_keputusan' => now(),
        ];
    }

    public function padaLevel(LevelPersetujuan $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
            'peran' => $level->peran()?->value ?? 'atasan',
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'keputusan' => Persetujuan::KEPUTUSAN_TOLAK,
            'catatan' => fake()->sentence(),
        ]);
    }

    public function revisi(): static
    {
        return $this->state(fn (array $attributes) => [
            'keputusan' => Persetujuan::KEPUTUSAN_REVISI,
            'catatan' => fake()->sentence(),
        ]);
    }
}
