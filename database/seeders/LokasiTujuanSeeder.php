<?php

namespace Database\Seeders;

use App\Models\LokasiTujuan;
use Illuminate\Database\Seeder;

/**
 * Kota tujuan perjalanan dinas.
 *
 * Disusun tiga lapis: Sulawesi Utara sebagai wilayah kerja terdekat, seluruh
 * ibu kota provinsi karena perjalanan dinas dapat menuju ke mana saja, lalu
 * sejumlah kota yang sering menjadi tempat pelatihan meski bukan ibu kota.
 *
 * Hanya Manado yang berjenis dalam kota — ia kedudukan Poltekkes Kemenkes
 * Manado. Penulisan yang seragam di sini membuat rekapitulasi per wilayah
 * tidak pecah oleh selisih ejaan.
 *
 * Menambah baris di sini aman bagi pemasangan yang sudah berjalan: pencocokan
 * memakai nama dan provinsi, sehingga kota yang sudah ada tidak terduplikasi.
 */
class LokasiTujuanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->kota() as [$nama, $provinsi]) {
            LokasiTujuan::updateOrCreate(
                ['nama' => $nama, 'provinsi' => $provinsi],
                [
                    'nama' => $nama,
                    'provinsi' => $provinsi,
                    'jenis' => $nama === 'Manado' ? 'dalam_kota' : 'luar_kota',
                    'is_aktif' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function kota(): array
    {
        return [
            // ── Sulawesi Utara: kota dan ibu kota kabupaten ──
            ['Manado', 'Sulawesi Utara'],
            ['Bitung', 'Sulawesi Utara'],
            ['Tomohon', 'Sulawesi Utara'],
            ['Kotamobagu', 'Sulawesi Utara'],
            ['Tondano', 'Sulawesi Utara'],
            ['Airmadidi', 'Sulawesi Utara'],
            ['Amurang', 'Sulawesi Utara'],
            ['Ratahan', 'Sulawesi Utara'],
            ['Lolak', 'Sulawesi Utara'],
            ['Boroko', 'Sulawesi Utara'],
            ['Tutuyan', 'Sulawesi Utara'],
            ['Molibagu', 'Sulawesi Utara'],
            ['Tahuna', 'Sulawesi Utara'],
            ['Ondong Siau', 'Sulawesi Utara'],
            ['Melonguane', 'Sulawesi Utara'],

            // ── Sulawesi dan sekitarnya ──
            ['Gorontalo', 'Gorontalo'],
            ['Palu', 'Sulawesi Tengah'],
            ['Makassar', 'Sulawesi Selatan'],
            ['Kendari', 'Sulawesi Tenggara'],
            ['Mamuju', 'Sulawesi Barat'],

            // ── Sumatera ──
            ['Banda Aceh', 'Aceh'],
            ['Medan', 'Sumatera Utara'],
            ['Padang', 'Sumatera Barat'],
            ['Pekanbaru', 'Riau'],
            ['Tanjungpinang', 'Kepulauan Riau'],
            ['Batam', 'Kepulauan Riau'],
            ['Jambi', 'Jambi'],
            ['Palembang', 'Sumatera Selatan'],
            ['Pangkalpinang', 'Kepulauan Bangka Belitung'],
            ['Bengkulu', 'Bengkulu'],
            ['Bandar Lampung', 'Lampung'],

            // ── Jawa dan Bali ──
            ['Jakarta', 'DKI Jakarta'],
            ['Bogor', 'Jawa Barat'],
            ['Depok', 'Jawa Barat'],
            ['Bekasi', 'Jawa Barat'],
            ['Bandung', 'Jawa Barat'],
            ['Serang', 'Banten'],
            ['Tangerang Selatan', 'Banten'],
            ['Semarang', 'Jawa Tengah'],
            ['Surakarta', 'Jawa Tengah'],
            ['Yogyakarta', 'DI Yogyakarta'],
            ['Surabaya', 'Jawa Timur'],
            ['Malang', 'Jawa Timur'],
            ['Denpasar', 'Bali'],

            // ── Nusa Tenggara ──
            ['Mataram', 'Nusa Tenggara Barat'],
            ['Kupang', 'Nusa Tenggara Timur'],

            // ── Kalimantan ──
            ['Pontianak', 'Kalimantan Barat'],
            ['Palangka Raya', 'Kalimantan Tengah'],
            ['Banjarbaru', 'Kalimantan Selatan'],
            ['Banjarmasin', 'Kalimantan Selatan'],
            ['Samarinda', 'Kalimantan Timur'],
            ['Balikpapan', 'Kalimantan Timur'],
            ['Tanjung Selor', 'Kalimantan Utara'],

            // ── Maluku dan Papua ──
            ['Ambon', 'Maluku'],
            ['Sofifi', 'Maluku Utara'],
            ['Ternate', 'Maluku Utara'],
            ['Jayapura', 'Papua'],
            ['Manokwari', 'Papua Barat'],
            ['Sorong', 'Papua Barat Daya'],
            ['Nabire', 'Papua Tengah'],
            ['Wamena', 'Papua Pegunungan'],
            ['Merauke', 'Papua Selatan'],
        ];
    }
}
