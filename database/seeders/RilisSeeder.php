<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Bekal awal peladen produksi: data master dan pegawai sungguhan saja.
 *
 * Berbeda dengan DatabaseSeeder yang juga menaburkan usulan contoh untuk
 * pengembangan, seeder ini sengaja tidak membuat satu pun data transaksi —
 * usulan pertama harus lahir dari pemakaian sungguhan.
 */
class RilisSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UnitKerjaSeeder::class,
            LokasiTujuanSeeder::class,
            KomponenBiayaSeeder::class,
            TahunAnggaranSeeder::class,
            KategoriPerjadinSeeder::class,

            // Pegawai Poltekkes Kemenkes Manado dari berkas DUK.
            // Kata sandi awal tiap akun adalah NIP-nya sendiri.
            PegawaiPoltekkesSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->warn('Kata sandi awal setiap akun sama dengan NIP-nya. Wajibkan penggantian sebelum dipakai.');
    }
}
