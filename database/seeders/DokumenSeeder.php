<?php

namespace Database\Seeders;

use App\Models\Dokumen;
use App\Models\Usulan;
use Illuminate\Database\Seeder;

class DokumenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Usulan::where('status', 'disetujui')->each(function (Usulan $usulan) {
            Dokumen::factory()->create(['id_usulan' => $usulan->id]);
        });
    }
}
