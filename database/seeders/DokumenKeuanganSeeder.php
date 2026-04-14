<?php

namespace Database\Seeders;

use App\Models\DokumenKeuangan;
use Illuminate\Database\Seeder;

class DokumenKeuanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DokumenKeuangan::factory(10)->create();
    }
}
