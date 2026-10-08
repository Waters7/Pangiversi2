<?php

namespace App\Console\Commands;

use App\Services\PemantauServer as Pemantau;
use Illuminate\Console\Command;

/**
 * Pemeriksaan kesehatan aplikasi tiap menit beserta peringatan WhatsApp-nya.
 *
 * Dipasang sebagai cron tersendiri, bukan lewat schedule:run: penjadwal
 * Laravel membaca cache dari basis data, sehingga ikut berhenti tepat saat
 * basis data terputus — saat peringatan paling dibutuhkan.
 *
 *   * * * * * cd /jalur/aplikasi && php artisan pangi:pantau-server >> /dev/null 2>&1
 */
class PantauServer extends Command
{
    protected $signature = 'pangi:pantau-server';

    protected $description = 'Periksa situs, basis data, dan penyimpanan; kirim peringatan WhatsApp bila aplikasi tidak dapat diakses';

    public function handle(Pemantau $pemantau): int
    {
        $kunci = $pemantau->kunci();

        if ($kunci === null) {
            $this->line('Pemeriksaan sebelumnya masih berjalan.');

            return self::SUCCESS;
        }

        try {
            $status = $pemantau->jalankan();
        } finally {
            flock($kunci, LOCK_UN);
            fclose($kunci);
        }

        foreach ($status['pemeriksaan'] as $hasil) {
            $this->line(($hasil['lolos'] ? '<info>LOLOS</info> ' : '<error>GAGAL</error> ').$hasil['label'].' — '.$hasil['pesan']);
        }

        $this->line('Status: '.$status['status'].($status['gagal_beruntun'] > 0 ? " (gagal {$status['gagal_beruntun']}x berturut-turut)" : ''));

        return $status['status'] === Pemantau::STATUS_NORMAL && $status['gagal_beruntun'] === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
