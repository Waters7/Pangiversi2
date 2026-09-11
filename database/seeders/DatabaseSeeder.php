<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Bekal awal basis data: data master dan pegawai sungguhan saja.
 *
 * Seeder ini sengaja tidak membuat satu pun usulan, dokumen, maupun catatan
 * keuangan. Dulu ada seeder contoh untuk keperluan pengembangan, tetapi satu
 * perintah `db:seed` yang telanjur dijalankan di peladen produksi cukup untuk
 * mencemari arsip perjalanan dinas dengan berkas yang tidak pernah ada.
 * Karena itu tidak ada lagi jalur yang dapat menaburkan data palsu — usulan
 * pertama harus lahir dari pemakaian sungguhan.
 *
 * Seluruh seeder di bawah ini memakai updateOrCreate, sehingga aman
 * dijalankan ulang pada pemasangan yang sudah berjalan: baris yang sudah ada
 * diperbarui, baris baru ditambahkan, dan tidak ada yang terduplikasi.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // Master data lebih dulu — dipakai sebagai rujukan oleh data lain.
            UnitKerjaSeeder::class,
            LokasiTujuanSeeder::class,
            KomponenBiayaSeeder::class,
            TahunAnggaranSeeder::class,
            KegiatanSeeder::class,
            KategoriPerjadinSeeder::class,

            // Pegawai Poltekkes Kemenkes Manado dari berkas DUK.
            PegawaiPoltekkesSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->warn(
            'Kata sandi awal setiap akun sama dengan NIP-nya. Wajibkan penggantian sebelum dipakai.'
        );
    }
}
