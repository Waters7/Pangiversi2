<?php

namespace Database\Factories;

use App\Models\Keuangan;
use App\Models\RincianBiaya;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RincianBiaya>
 */
class RincianBiayaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $volume = fake()->numberBetween(1, 5);
        $harga = fake()->randomElement([150000, 200000, 350000, 500000, 750000, 1000000, 1500000]);

        return [
            'komponen' => fake()->randomElement([
                'Uang harian',
                'Tiket pesawat PP',
                'Penginapan/hotel',
                'Transportasi lokal',
                'Uang representasi',
                'Biaya pendaftaran seminar',
                'Sewa kendaraan',
                'Airport tax',
            ]),
            'volume' => $volume,
            'satuan' => fake()->randomElement(['OH', 'OK', 'OB', 'Tiket', 'Paket', 'Hari', 'Kali']),
            'harga_satuan' => $harga,
            'jumlah' => $volume * $harga,
            'id_keuangan' => Keuangan::factory(),
        ];
    }
}
