<?php

namespace Database\Seeders;

use App\Models\LokasiTujuan;
use Illuminate\Database\Seeder;

class LokasiTujuanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lokasi = [
            ['nama' => 'Manado', 'provinsi' => 'Sulawesi Utara', 'jenis' => 'dalam_kota'],
            ['nama' => 'Tomohon', 'provinsi' => 'Sulawesi Utara', 'jenis' => 'luar_kota'],
            ['nama' => 'Bitung', 'provinsi' => 'Sulawesi Utara', 'jenis' => 'luar_kota'],
            ['nama' => 'Kotamobagu', 'provinsi' => 'Sulawesi Utara', 'jenis' => 'luar_kota'],
            ['nama' => 'Jakarta', 'provinsi' => 'DKI Jakarta', 'jenis' => 'luar_kota'],
            ['nama' => 'Bandung', 'provinsi' => 'Jawa Barat', 'jenis' => 'luar_kota'],
            ['nama' => 'Yogyakarta', 'provinsi' => 'DI Yogyakarta', 'jenis' => 'luar_kota'],
            ['nama' => 'Surabaya', 'provinsi' => 'Jawa Timur', 'jenis' => 'luar_kota'],
            ['nama' => 'Makassar', 'provinsi' => 'Sulawesi Selatan', 'jenis' => 'luar_kota'],
            ['nama' => 'Denpasar', 'provinsi' => 'Bali', 'jenis' => 'luar_kota'],
            ['nama' => 'Gorontalo', 'provinsi' => 'Gorontalo', 'jenis' => 'luar_kota'],
            ['nama' => 'Palu', 'provinsi' => 'Sulawesi Tengah', 'jenis' => 'luar_kota'],
        ];

        foreach ($lokasi as $data) {
            LokasiTujuan::updateOrCreate(
                ['nama' => $data['nama'], 'provinsi' => $data['provinsi']],
                $data + ['is_aktif' => true]
            );
        }
    }
}
