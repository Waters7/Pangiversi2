<?php

namespace Database\Seeders;

use App\Models\KategoriPerjadin;
use Illuminate\Database\Seeder;

/**
 * Daftar kategori perjalanan dinas Poltekkes Kemenkes Manado.
 *
 * Grup Auditor belum dilengkapi karena daftar acuannya terpotong; kategorinya
 * dapat ditambahkan kemudian lewat basis data tanpa mengubah kode.
 */
class KategoriPerjadinSeeder extends Seeder
{
    public function run(): void
    {
        // Penanda ketiga menyatakan perjalanannya dalam kota, yang menentukan
        // berkas apa yang ditagih sistem: dalam kota cukup SPPD dan nota
        // transportasi lokal, tanpa tiket, penginapan, maupun kuitansi.
        $kategori = [
            'Perjadin Luar Kota' => [
                ['LK-NFB', 'Luar Kota NonFullBoard', false],
                ['LK-FB', 'Luar Kota FullBoard', false],
                ['LN', 'Luar Negeri', false],
            ],
            'Perjadin Dalam Kota' => [
                ['DK-FB', 'Dalam Kota FullBoard', true],
                ['DK-FD', 'Dalam Kota FullDay', true],
                ['DK-8J', 'Dalam Kota Lebih dari 8 Jam', true],
                ['DK-HDP', 'Dalam Kota HalfDay Pagi', true],
                ['DK-HDS', 'Dalam Kota HalfDay Sore', true],
                ['TL', 'Transport Lokal', true],
            ],
            // Dua grup berikut memuat keduanya, jadi penandanya mengikuti
            // nama kategorinya — bukan grupnya.
            'Narasumber' => [
                ['NS-DK-FD', 'Narasumber Dalam Kota Fullday', true],
                ['NS-LK-FB', 'Narasumber Luar Kota FullBoard', false],
                ['NS-DAR', 'Narasumber (Daring)', true],
                ['NS-LK-NFB', 'Narasumber Luar Kota NonFullBoard', false],
            ],
            'Diklat' => [
                ['DL-LK-NFB', 'Diklat Luar Kota NonFullBoard', false],
                ['DL-LK-FB', 'Diklat Luar Kota FullBoard', false],
                ['DL-DK', 'Diklat Dalam Kota', true],
            ],
        ];

        $urutan = 0;

        foreach ($kategori as $grup => $daftar) {
            foreach ($daftar as [$kode, $nama, $dalamKota]) {
                KategoriPerjadin::updateOrCreate(
                    ['kode' => $kode],
                    [
                        'grup' => $grup,
                        'nama' => $nama,
                        'dalam_kota' => $dalamKota,
                        'urutan' => ++$urutan,
                        'is_aktif' => true,
                    ],
                );
            }
        }
    }
}
