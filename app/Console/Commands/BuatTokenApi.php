<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Terbitkan token acak untuk API PANGI.
 *
 * Tokennya hanya dicetak, tidak ditulis ke .env: berkas .env di server
 * produksi kerap berbeda dari salinan lokal, dan menimpanya otomatis lebih
 * mudah merusak daripada menolong.
 */
class BuatTokenApi extends Command
{
    protected $signature = 'pangi:token-api';

    protected $description = 'Buat token acak untuk dipasang pada PANGI_API_TOKEN';

    public function handle(): int
    {
        $token = Str::random(64);

        $this->newLine();
        $this->line('  Pasang baris berikut pada berkas .env server PANGI:');
        $this->newLine();
        $this->line('  <fg=green>PANGI_API_TOKEN='.$token.'</>');
        $this->newLine();
        $this->line('  Lalu jalankan: <fg=yellow>php artisan config:cache</>');
        $this->newLine();
        $this->comment('  Kirimkan token ini kepada pengembang aplikasi pemanggil lewat');
        $this->comment('  jalur yang aman — jangan lewat grup obrolan bersama.');
        $this->newLine();

        return self::SUCCESS;
    }
}
