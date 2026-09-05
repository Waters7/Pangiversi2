<?php

namespace App\Console\Commands;

use App\Services\PengingatDokumen;
use Illuminate\Console\Command;

class KirimPengingatDokumen extends Command
{
    protected $signature = 'pangi:pengingat-dokumen';

    protected $description = 'Kirim notifikasi kepada pegawai yang berkas pertanggungjawaban perjalanan dinasnya belum lengkap';

    public function handle(PengingatDokumen $pengingat): int
    {
        $hasil = $pengingat->jalankan();

        $this->info("Pengingat terkirim: {$hasil['terkirim']}, dilewati: {$hasil['dilewati']}.");

        return self::SUCCESS;
    }
}
