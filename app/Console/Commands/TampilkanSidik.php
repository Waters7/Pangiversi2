<?php

namespace App\Console\Commands;

use App\Services\SidikAplikasi;
use Illuminate\Console\Command;

/**
 * Membuka sidik keaslian kode sumber. Sengaja disembunyikan dari daftar
 * `php artisan list`.
 */
class TampilkanSidik extends Command
{
    protected $signature = 'pangi:sidik';

    protected $description = 'Buka sidik keaslian kode sumber';

    protected $hidden = true;

    public function handle(SidikAplikasi $sidik): int
    {
        $teks = $sidik->teks();

        if ($teks === '') {
            $this->error('Sidik tidak dapat dibuka — isinya telah diubah.');

            return self::FAILURE;
        }

        $this->line($teks);
        $this->line('SHA-256: '.$sidik->hash());

        return self::SUCCESS;
    }
}
