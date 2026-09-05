<?php

namespace Database\Seeders;

use App\Models\KomponenBiaya;
use Illuminate\Database\Seeder;

class KomponenBiayaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $komponen = [
            ['nama' => 'Uang Harian', 'satuan' => 'OH', 'harga_satuan' => 430_000, 'jenis' => 'sbm'],
            ['nama' => 'Uang Harian Dalam Kota', 'satuan' => 'OH', 'harga_satuan' => 150_000, 'jenis' => 'sbm'],
            ['nama' => 'Penginapan', 'satuan' => 'OH', 'harga_satuan' => 1_200_000, 'jenis' => 'at_cost'],
            ['nama' => 'Tiket Pesawat PP', 'satuan' => 'OK', 'harga_satuan' => 3_500_000, 'jenis' => 'at_cost'],
            ['nama' => 'Transport Lokal', 'satuan' => 'OK', 'harga_satuan' => 200_000, 'jenis' => 'sbm'],
            ['nama' => 'Transport Bandara PP', 'satuan' => 'OK', 'harga_satuan' => 250_000, 'jenis' => 'sbm'],
            ['nama' => 'Representasi', 'satuan' => 'OH', 'harga_satuan' => 250_000, 'jenis' => 'sbm'],
            ['nama' => 'Biaya Kontribusi Kegiatan', 'satuan' => 'Paket', 'harga_satuan' => 1_500_000, 'jenis' => 'at_cost'],
        ];

        foreach ($komponen as $data) {
            KomponenBiaya::updateOrCreate(['nama' => $data['nama']], $data + ['is_aktif' => true]);
        }
    }
}
