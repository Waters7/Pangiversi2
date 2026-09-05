<?php

namespace Database\Seeders;

use App\Models\Kegiatan;
use Illuminate\Database\Seeder;

class KegiatanSeeder extends Seeder
{
    /**
     * Jenis kegiatan perjalanan dinas yang berlaku di lingkungan
     * Poltekkes Kemenkes Manado.
     *
     * @var list<string>
     */
    private const JENIS = [
        'Mengikuti rapat, seminar, lokakarya, atau studi banding',
        'Melaksanakan tugas dan fungsi jabatan atau kunjungan kerja',
        'Mengikuti pendidikan dan pelatihan (diklat) atau kursus singkat',
        'Menempuh ujian dinas atau menghadap majelis penguji kesehatan',
        'Mengikuti pameran, promosi, atau sidang internasional',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::JENIS as $nama) {
            Kegiatan::updateOrCreate(['nama' => $nama]);
        }
    }
}
