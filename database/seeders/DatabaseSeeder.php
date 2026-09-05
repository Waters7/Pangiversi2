<?php

namespace Database\Seeders;

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
            // Master data lebih dulu — dipakai sebagai referensi oleh data transaksi.
            UnitKerjaSeeder::class,
            LokasiTujuanSeeder::class,
            KomponenBiayaSeeder::class,
            TahunAnggaranSeeder::class,

            // Pegawai nyata dari DUK Agustus 2026; UserSeeder lama hanya
            // dipakai bila berkas DUK-nya tidak tersedia.
            PegawaiPoltekkesSeeder::class,
            KegiatanSeeder::class,
            KategoriPerjadinSeeder::class,
            UsulanSeeder::class,
            PesertaUsulanSeeder::class,
            DokumenSeeder::class,
            KeuanganSeeder::class,
            RincianBiayaSeeder::class,
            DokumenKeuanganSeeder::class,
            DaftarRiilSeeder::class,
        ]);
    }
}
