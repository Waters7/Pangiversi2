<?php

namespace App\Console\Commands;

use App\Models\PengirimanIntegrasi;
use App\Services\PengirimIntegrasi;
use Illuminate\Console\Command;

/**
 * Dijalankan penjadwal tiap menit; baru benar-benar mengirim bila jadwal
 * pada menu Integrasi Data sudah jatuh tempo. Opsi --paksa mengirim
 * seketika, misalnya untuk mencoba alamat tujuan dari terminal.
 */
class KirimIntegrasiData extends Command
{
    protected $signature = 'pangi:kirim-integrasi {--paksa : Kirim sekarang tanpa menunggu jadwal}';

    protected $description = 'Kirim data dashboard eksekutif ke aplikasi tujuan sesuai jadwal Integrasi Data';

    public function handle(PengirimIntegrasi $pengirim): int
    {
        if (! $this->option('paksa')) {
            if (! $pengirim->siap()) {
                $this->line('Pengiriman terjadwal tidak aktif atau alamat tujuan belum diisi.');

                return self::SUCCESS;
            }

            if (! $pengirim->jatuhTempo()) {
                $this->line('Belum jatuh tempo; berikutnya '.$pengirim->jadwalBerikutnya()?->translatedFormat('d M Y H:i').'.');

                return self::SUCCESS;
            }
        }

        $hasil = $pengirim->kirim(PengirimanIntegrasi::PEMICU_JADWAL);

        if ($hasil->berhasil()) {
            $this->info("Terkirim ke {$hasil->tujuan} (HTTP {$hasil->kode_http}, {$hasil->ukuran_byte} byte, {$hasil->durasi_ms} ms).");

            return self::SUCCESS;
        }

        $this->error('Gagal: '.$hasil->pesan);

        return self::FAILURE;
    }
}
