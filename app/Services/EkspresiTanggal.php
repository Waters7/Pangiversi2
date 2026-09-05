<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Potongan SQL tanggal yang menyesuaikan diri dengan mesin basis data.
 *
 * SQLite memakai strftime(), MySQL dan MariaDB memakai DATE_FORMAT, dan
 * PostgreSQL memakai TO_CHAR. Pengembangan berjalan di atas SQLite sementara
 * peladen produksi memakai MySQL, jadi ekspresinya dipusatkan di sini supaya
 * laporan dan dashboard tidak pecah saat basis datanya berbeda.
 */
class EkspresiTanggal
{
    /**
     * Nomor bulan sebagai bilangan bulat, untuk dikelompokkan.
     */
    public function bulan(string $kolom): string
    {
        return match ($this->driver()) {
            'sqlite' => "CAST(strftime('%m', {$kolom}) AS INTEGER)",
            'pgsql' => "CAST(TO_CHAR({$kolom}, 'MM') AS INTEGER)",
            default => "CAST(DATE_FORMAT({$kolom}, '%m') AS UNSIGNED)",
        };
    }

    /**
     * Tahun sebagai bilangan bulat, untuk dikelompokkan.
     */
    public function tahun(string $kolom): string
    {
        return match ($this->driver()) {
            'sqlite' => "CAST(strftime('%Y', {$kolom}) AS INTEGER)",
            'pgsql' => "CAST(TO_CHAR({$kolom}, 'YYYY') AS INTEGER)",
            default => "CAST(DATE_FORMAT({$kolom}, '%Y') AS UNSIGNED)",
        };
    }

    /**
     * Periode tahun-bulan sebagai teks "2026-08".
     */
    public function tahunBulan(string $kolom): string
    {
        return match ($this->driver()) {
            'sqlite' => "strftime('%Y-%m', {$kolom})",
            'pgsql' => "TO_CHAR({$kolom}, 'YYYY-MM')",
            default => "DATE_FORMAT({$kolom}, '%Y-%m')",
        };
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
