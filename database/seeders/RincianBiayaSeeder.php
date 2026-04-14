<?php

namespace Database\Seeders;

use App\Models\Keuangan;
use App\Models\RincianBiaya;
use Illuminate\Database\Seeder;

class RincianBiayaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Keuangan::all()->each(function (Keuangan $keuangan) {
            RincianBiaya::factory()
                ->count(fake()->numberBetween(3, 6))
                ->create(['id_keuangan' => $keuangan->id]);

            $keuangan->hitungTotal();
        });
    }
}
