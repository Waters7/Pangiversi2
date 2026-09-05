<?php

namespace Database\Seeders;

use App\Enums\KategoriBiaya;
use App\Models\Keuangan;
use App\Models\RincianBiaya;
use Illuminate\Database\Seeder;

class RincianBiayaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Susunan komponennya mengikuti pola perjalanan dinas yang sebenarnya:
     * tiket, uang harian, dan penginapan selalu ada, sedangkan transport
     * lokal dan biaya lainnya bersifat sesekali. Tanpa pola ini, dokumen
     * rincian PMK dan daftar nominatif tidak punya isi di kolomnya.
     */
    public function run(): void
    {
        Keuangan::all()->each(function (Keuangan $keuangan): void {
            $kategori = [
                KategoriBiaya::Transport,
                KategoriBiaya::UangHarian,
                KategoriBiaya::Penginapan,
            ];

            if (fake()->boolean(70)) {
                $kategori[] = KategoriBiaya::TransportLokal;
            }

            if (fake()->boolean(25)) {
                $kategori[] = KategoriBiaya::Lainnya;
            }

            foreach ($kategori as $item) {
                RincianBiaya::factory()
                    ->kategori($item)
                    ->create(['id_keuangan' => $keuangan->id]);
            }

            $keuangan->hitungTotal();
        });
    }
}
