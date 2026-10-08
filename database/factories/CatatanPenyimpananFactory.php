<?php

namespace Database\Factories;

use App\Models\CatatanPenyimpanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatatanPenyimpanan>
 */
class CatatanPenyimpananFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unggahan = fake()->numberBetween(50, 900) * 1024 * 1024;
        $basisData = fake()->numberBetween(5, 120) * 1024 * 1024;
        $pustaka = 80 * 1024 * 1024;

        return [
            'tanggal' => fake()->unique()->dateTimeBetween('-60 days')->format('Y-m-d'),
            'total_byte' => $unggahan + $basisData + $pustaka,
            'unggahan_byte' => $unggahan,
            'basis_data_byte' => $basisData,
            'rincian' => [
                'kelompok' => [
                    'unggahan' => ['byte' => $unggahan, 'berkas' => 1200],
                    'basis_data' => ['byte' => $basisData, 'berkas' => 1],
                    'pustaka' => ['byte' => $pustaka, 'berkas' => 9000],
                ],
                'unggahan' => [],
                'tabel' => [],
                'terbesar' => [],
                'disk' => ['total' => null, 'bebas' => null],
                'lama_detik' => 1.2,
            ],
        ];
    }
}
