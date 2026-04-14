<?php

namespace Database\Seeders;

use App\Models\Keuangan;
use App\Models\Usulan;
use Illuminate\Database\Seeder;

class KeuanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Usulan::where('status', 'disetujui')->each(function (Usulan $usulan) {
            Keuangan::factory()->create(['id_usulan' => $usulan->id]);
        });
    }
}
