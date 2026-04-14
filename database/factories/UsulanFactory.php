<?php

namespace Database\Factories;

use App\Models\Kegiatan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usulan>
 */
class UsulanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'no_usulan' => $this->faker->unique()->numerify('USL-2025-###'),
            'no_tugas' => $this->faker->unique()->numerify('TGS-2025-###'),
            'status' => $this->faker->numberBetween(1, 100) <= 40
                            ? 'disetujui'
                            : $this->faker->randomElement([
                                'draft',
                                'diajukan',
                                'menunggu',
                                'ditolak',
                                'selesai',
                            ]),
            'lokasi' => $this->faker->city(),
            'instansi' => $this->faker->company(),
            'tanggal_mulai' => $this->faker->date(),
            'tanggal_selesai' => $this->faker->date(),
            'uraian' => $this->faker->paragraph(),
            'id_user' => User::get()->random()->id,
            'id_kegiatan' => Kegiatan::factory(),
        ];
    }
}
