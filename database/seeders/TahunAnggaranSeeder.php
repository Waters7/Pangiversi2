<?php

namespace Database\Seeders;

use App\Models\TahunAnggaran;
use Illuminate\Database\Seeder;

class TahunAnggaranSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tahunBerjalan = now()->year;

        foreach ([$tahunBerjalan - 1, $tahunBerjalan, $tahunBerjalan + 1] as $tahun) {
            TahunAnggaran::updateOrCreate(
                ['tahun' => $tahun],
                [
                    'pagu' => 2_500_000_000,
                    'is_aktif' => $tahun === $tahunBerjalan,
                ]
            );
        }
    }
}
