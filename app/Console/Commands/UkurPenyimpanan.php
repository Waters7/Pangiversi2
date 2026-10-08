<?php

namespace App\Console\Commands;

use App\Models\CatatanPenyimpanan;
use App\Services\PengukurPenyimpanan;
use Illuminate\Console\Command;

/**
 * Catatan harian ukuran aplikasi untuk menu Pemantauan Server — dari sini
 * pertumbuhan berkas unggahan dan basis data dapat diikuti.
 */
class UkurPenyimpanan extends Command
{
    protected $signature = 'pangi:ukur-penyimpanan';

    protected $description = 'Ukur ukuran aplikasi (unggahan, basis data, log, pustaka) dan simpan catatan hari ini';

    public function handle(PengukurPenyimpanan $pengukur): int
    {
        $catatan = $pengukur->catat();

        foreach ($catatan->rincian['kelompok'] as $kunci => $isi) {
            $this->line(str_pad(PengukurPenyimpanan::KELOMPOK[$kunci] ?? $kunci, 40).CatatanPenyimpanan::ukuranTerbaca($isi['byte']));
        }

        $this->info('Total '.CatatanPenyimpanan::ukuranTerbaca($catatan->total_byte).' — diukur dalam '.number_format((float) $catatan->rincian['lama_detik'], 1, ',', '.').' detik.');

        return self::SUCCESS;
    }
}
