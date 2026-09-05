<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Peladen yang sudah menjalankan PANGI versi sebelumnya tidak boleh gagal
 * bermigrasi hanya karena sebuah berkas migrasi diurutkan ulang.
 */
class MigrasiLanjutanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Berkas migrasi kegiatan sempat berganti nama agar berjalan sebelum
     * tabel usulan. Di peladen lama namanya tercatat dengan nama yang lama,
     * sehingga Laravel menganggapnya migrasi baru dan menjalankannya lagi.
     */
    public function test_migrasi_kegiatan_aman_dijalankan_ulang(): void
    {
        $this->assertTrue(Schema::hasTable('kegiatan'));

        $migrasi = require database_path('migrations/2026_04_11_035304_create_kegiatans_table.php');

        $migrasi->up();

        $this->assertTrue(Schema::hasTable('kegiatan'));
    }

    public function test_tabel_kegiatan_dibuat_sebelum_usulan_merujuknya(): void
    {
        $berkas = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $jalur): string => basename($jalur))
            ->sort()
            ->values();

        $urutanKegiatan = $berkas->search(fn (string $n): bool => str_contains($n, 'create_kegiatans_table'));
        $urutanUsulan = $berkas->search(fn (string $n): bool => str_contains($n, 'create_usulan_table'));

        $this->assertNotFalse($urutanKegiatan);
        $this->assertNotFalse($urutanUsulan);
        $this->assertLessThan(
            $urutanUsulan,
            $urutanKegiatan,
            'Tabel kegiatan harus dibuat lebih dulu; MySQL menolak kunci asing ke tabel yang belum ada.'
        );
    }
}
