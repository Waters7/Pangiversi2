<?php

namespace App\Console\Commands;

use App\Models\Pengaturan;
use App\Services\SinkronMappalus;
use Illuminate\Console\Command;

/**
 * Dijalankan penjadwal; mengirim data baru dan mengulang yang gagal sampai
 * batas percobaannya. Data yang sudah diterima Mappalus tidak terkirim ulang.
 */
class SinkronkanMappalus extends Command
{
    protected $signature = 'pangi:sinkron-mappalus';

    protected $description = 'Kirim daftar nominatif bertanda tangan ke Mappalus, per pelaksana';

    public function handle(SinkronMappalus $sinkron): int
    {
        if (! Pengaturan::aktif(Pengaturan::MAPPALUS_AKTIF) || ! $sinkron->siap()) {
            $this->line('Pengiriman otomatis Mappalus tidak aktif, atau alamat dan tokennya belum diisi.');

            return self::SUCCESS;
        }

        $hasil = $sinkron->sinkron(otomatis: true);

        if ($hasil === null) {
            $this->line('Sinkron lain sedang berjalan.');

            return self::SUCCESS;
        }

        $this->info("{$hasil['berhasil']} berhasil, {$hasil['gagal']} gagal, {$hasil['sisa']} tersisa.");

        return self::SUCCESS;
    }
}
