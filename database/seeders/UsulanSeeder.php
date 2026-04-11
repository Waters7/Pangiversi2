<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Usulan;
use Illuminate\Database\Seeder;

class UsulanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Usulan::factory()->count(10)->create();
    }

    
}
