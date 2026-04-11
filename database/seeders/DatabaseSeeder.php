<?php

namespace Database\Seeders;


use Database\Seeders\KegiatanSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\UsulanSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            KegiatanSeeder::class,
            UsulanSeeder::class,
        ]);
    }
}
