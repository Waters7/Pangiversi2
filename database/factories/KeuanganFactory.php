<?php

namespace Database\Factories;

use App\Models\Keuangan;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Keuangan>
 */
class KeuanganFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['bayar sebagian', 'lunas', 'belum bayar']);

        return [
            'tanggal_transfer' => $status !== 'belum bayar' ? fake()->dateTimeBetween('-30 days', 'now') : null,
            'tanggal_pelunasan' => $status === 'lunas' ? fake()->dateTimeBetween('-7 days', 'now') : null,
            'total' => 0,
            'uang_muka' => 0,
            'sisa' => 0,
            'status' => $status,
            'id_usulan' => Usulan::factory(),
        ];
    }
}
