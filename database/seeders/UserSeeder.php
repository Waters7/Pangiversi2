<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 1 administrator
        User::factory()->administrator()->create([
            'nama' => 'Admin PANGI',
            'email' => 'admin@poltekkes.ac.id',
            'nip' => 'NIP-0000000001',
        ]);

        // Create 2 PPK users
        User::factory()->ppk()->count(2)->create();

        // Create 7 regular pegawai
        User::factory()->count(7)->create();
    }
}
