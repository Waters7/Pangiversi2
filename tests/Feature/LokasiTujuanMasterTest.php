<?php

namespace Tests\Feature;

use App\Models\LokasiTujuan;
use Database\Seeders\LokasiTujuanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar kota tujuan perjalanan dinas.
 *
 * Penulisan kota yang seragam menentukan apakah rekapitulasi per wilayah
 * utuh atau pecah oleh selisih ejaan, jadi daftarnya disemai dari master data
 * dan bukan diketik bebas tiap kali mengajukan.
 */
class LokasiTujuanMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_seluruh_ibu_kota_provinsi_tersedia(): void
    {
        $this->seed(LokasiTujuanSeeder::class);

        // Tiga puluh delapan provinsi, masing-masing ibu kotanya.
        foreach ([
            'Banda Aceh', 'Medan', 'Padang', 'Pekanbaru', 'Tanjungpinang', 'Jambi',
            'Palembang', 'Pangkalpinang', 'Bengkulu', 'Bandar Lampung', 'Jakarta',
            'Bandung', 'Serang', 'Semarang', 'Yogyakarta', 'Surabaya', 'Denpasar',
            'Mataram', 'Kupang', 'Pontianak', 'Palangka Raya', 'Banjarbaru',
            'Samarinda', 'Tanjung Selor', 'Manado', 'Gorontalo', 'Palu', 'Mamuju',
            'Makassar', 'Kendari', 'Ambon', 'Sofifi', 'Jayapura', 'Manokwari',
            'Sorong', 'Nabire', 'Wamena', 'Merauke',
        ] as $kota) {
            $this->assertDatabaseHas('lokasi_tujuan', ['nama' => $kota, 'is_aktif' => true]);
        }
    }

    /** Wilayah kerja terdekat: kota dan ibu kota kabupaten se-Sulawesi Utara. */
    public function test_kota_sulawesi_utara_lengkap(): void
    {
        $this->seed(LokasiTujuanSeeder::class);

        foreach ([
            'Bitung', 'Tomohon', 'Kotamobagu', 'Tondano', 'Airmadidi', 'Amurang',
            'Ratahan', 'Lolak', 'Boroko', 'Tutuyan', 'Molibagu', 'Tahuna',
            'Ondong Siau', 'Melonguane',
        ] as $kota) {
            $this->assertDatabaseHas('lokasi_tujuan', [
                'nama' => $kota,
                'provinsi' => 'Sulawesi Utara',
            ]);
        }
    }

    /** Manado kedudukan Poltekkes, jadi hanya ia yang berjenis dalam kota. */
    public function test_hanya_manado_yang_berjenis_dalam_kota(): void
    {
        $this->seed(LokasiTujuanSeeder::class);

        $this->assertSame(
            ['Manado'],
            LokasiTujuan::where('jenis', 'dalam_kota')->pluck('nama')->all(),
        );
    }

    /**
     * Seeder ini dijalankan ulang pada pemasangan yang sudah berjalan untuk
     * menambah kota baru, jadi ia tidak boleh menggandakan yang sudah ada.
     */
    public function test_dijalankan_dua_kali_tidak_menggandakan(): void
    {
        $this->seed(LokasiTujuanSeeder::class);
        $sekali = LokasiTujuan::count();

        $this->seed(LokasiTujuanSeeder::class);

        $this->assertSame($sekali, LokasiTujuan::count());
        $this->assertGreaterThan(50, $sekali, 'Daftar kota seharusnya jauh lebih panjang dari sebelumnya.');
    }
}
