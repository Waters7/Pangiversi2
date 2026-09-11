<?php

namespace Database\Factories;

use App\Enums\StatusUsulan;
use App\Models\KategoriPerjadin;
use App\Models\Kegiatan;
use App\Models\LokasiTujuan;
use App\Models\TahunAnggaran;
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
        $lokasi = LokasiTujuan::inRandomOrder()->first();

        // Perjalanan dinas berdurasi wajar dan berada di sekitar tahun berjalan.
        $mulai = $this->faker->dateTimeBetween('-6 months', '+3 months');
        $selesai = (clone $mulai)->modify('+'.$this->faker->numberBetween(1, 5).' days');

        return [
            'no_usulan' => $this->faker->unique()->numerify('USL-2025-###'),
            'no_tugas' => $this->faker->unique()->numerify('TGS-2025-###'),
            'no_spd' => $this->faker->unique()->numerify('KU.02.04/F.XXX.8/###/2026'),
            'status' => $this->faker->numberBetween(1, 100) <= 40
                            ? StatusUsulan::Disetujui->value
                            : $this->faker->randomElement([
                                StatusUsulan::Draft->value,
                                StatusUsulan::MenungguPpk->value,
                                StatusUsulan::Ditolak->value,
                                StatusUsulan::Selesai->value,
                            ]),
            'lokasi' => $lokasi?->nama ?? $this->faker->city(),
            'id_lokasi' => $lokasi?->id,
            'instansi' => $this->faker->company(),
            'tanggal_mulai' => $mulai->format('Y-m-d'),
            'tanggal_selesai' => $selesai->format('Y-m-d'),
            'uraian' => $this->faker->paragraph(),
            'id_user' => User::inRandomOrder()->value('id') ?? User::factory(),
            // Pakai jenis kegiatan yang sudah ada agar master data tidak terisi
            // nama acak; buat baru hanya bila tabelnya masih kosong.
            'id_kegiatan' => Kegiatan::inRandomOrder()->value('id') ?? Kegiatan::factory(),
            'id_kategori_perjadin' => KategoriPerjadin::inRandomOrder()->value('id'),
            'id_tahun_anggaran' => TahunAnggaran::aktif()?->id,
        ];
    }
}
