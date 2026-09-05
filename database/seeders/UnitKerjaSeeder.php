<?php

namespace Database\Seeders;

use App\Models\UnitKerja;
use Illuminate\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $unit = [
            ['kode' => 'DIR', 'nama' => 'Direktorat', 'keterangan' => 'Pimpinan dan unsur pimpinan'],
            ['kode' => 'ADUM', 'nama' => 'Bagian Administrasi Umum', 'keterangan' => 'Kepegawaian, keuangan, dan umum'],
            ['kode' => 'AKAD', 'nama' => 'Bagian Akademik dan Kemahasiswaan', 'keterangan' => 'Layanan akademik dan kemahasiswaan'],
            ['kode' => 'KEP', 'nama' => 'Jurusan Keperawatan', 'keterangan' => null],
            ['kode' => 'BID', 'nama' => 'Jurusan Kebidanan', 'keterangan' => null],
            ['kode' => 'GIZ', 'nama' => 'Jurusan Gizi', 'keterangan' => null],
            ['kode' => 'KESLING', 'nama' => 'Jurusan Kesehatan Lingkungan', 'keterangan' => null],
            ['kode' => 'FAR', 'nama' => 'Jurusan Farmasi', 'keterangan' => null],
            ['kode' => 'ANKES', 'nama' => 'Jurusan Teknologi Laboratorium Medis', 'keterangan' => null],
            ['kode' => 'GIGI', 'nama' => 'Jurusan Kesehatan Gigi', 'keterangan' => null],
        ];

        foreach ($unit as $data) {
            UnitKerja::updateOrCreate(['kode' => $data['kode']], $data + ['is_aktif' => true]);
        }
    }
}
