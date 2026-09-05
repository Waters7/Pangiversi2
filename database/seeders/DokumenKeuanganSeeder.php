<?php

namespace Database\Seeders;

use App\Models\DokumenKeuangan;
use App\Models\Keuangan;
use Illuminate\Database\Seeder;

class DokumenKeuanganSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Setiap catatan keuangan punya tepat satu berkas bukti transfer.
        Keuangan::all()->each(function (Keuangan $keuangan): void {
            DokumenKeuangan::factory()->create(['id_keuangan' => $keuangan->id]);
        });
    }
}
