<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Menyiapkan aplikasi agar dapat dibuka dari perangkat lain di jaringan lokal.
 *
 * Alamat IP perangkat pada jaringan kantor umumnya diberikan DHCP dan berganti
 * sendiri. Bila APP_URL masih menunjuk alamat lama, tautan pada notifikasi
 * yang dibuat penjadwal akan mengarah ke alamat yang sudah tidak dipakai.
 * Perintah ini menyelaraskan keduanya sebelum server dijalankan.
 */
class SiapkanServerLan extends Command
{
    protected $signature = 'pangi:siapkan-lan
                            {--port=8000 : Nomor porta yang dipakai server}
                            {--ip= : Tetapkan alamat IP secara manual bila deteksi otomatis meleset}';

    protected $description = 'Selaraskan APP_URL dengan alamat IP jaringan lokal sebelum server diuji bersama';

    public function handle(): int
    {
        $porta = (int) $this->option('port');
        $ip = $this->option('ip') ?: $this->alamatLokal();

        if ($ip === null) {
            $this->components->error('Alamat IP jaringan lokal tidak terbaca. Jalankan ulang dengan --ip=alamat.');

            return self::FAILURE;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->components->error("Alamat \"{$ip}\" bukan alamat IPv4 yang sah.");

            return self::FAILURE;
        }

        $alamat = "http://{$ip}:{$porta}";
        $sebelum = config('app.url');

        if (! $this->perbaruiEnv($alamat)) {
            $this->components->warn('Berkas .env tidak ditemukan; APP_URL dibiarkan apa adanya.');
        }

        $this->tampilkan($ip, $porta, $alamat, $sebelum);

        return self::SUCCESS;
    }

    /**
     * Alamat IP perangkat ini pada jaringan lokal.
     *
     * Membuka soket UDP ke alamat luar lalu membaca nama soket lokalnya.
     * Tidak ada paket yang benar-benar dikirim, tetapi sistem operasi
     * terlanjur memilih antarmuka jaringan yang dipakai untuk keluar —
     * sehingga cara ini menunjuk adapter yang benar meski ada adapter maya
     * dari mesin virtual atau VPN.
     */
    private function alamatLokal(): ?string
    {
        $soket = @stream_socket_client('udp://8.8.8.8:53', $galat, $pesan, 1);

        if ($soket !== false) {
            $nama = stream_socket_get_name($soket, false);
            fclose($soket);

            $ip = strstr((string) $nama, ':', true) ?: null;

            if ($ip !== null && $ip !== '0.0.0.0') {
                return $ip;
            }
        }

        // Cadangan bila jaringan tertutup sama sekali.
        $ip = gethostbyname(gethostname());

        return $ip !== gethostname() && $ip !== '127.0.0.1' ? $ip : null;
    }

    private function perbaruiEnv(string $alamat): bool
    {
        $berkas = base_path('.env');

        if (! File::exists($berkas)) {
            return false;
        }

        $isi = File::get($berkas);
        $baris = 'APP_URL='.$alamat;

        $hasil = preg_match('/^APP_URL=.*$/m', $isi)
            ? preg_replace('/^APP_URL=.*$/m', $baris, $isi, 1)
            : rtrim($isi)."\n".$baris."\n";

        File::put($berkas, $hasil);

        // Cache konfigurasi memuat APP_URL lama; tanpa ini alamatnya tidak berubah.
        $this->callSilently('config:clear');

        return true;
    }

    private function tampilkan(string $ip, int $porta, string $alamat, ?string $sebelum): void
    {
        $this->newLine();
        $this->line('  <fg=black;bg=green> PANGI siap diuji bersama </>');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=gray>Di perangkat ini</>', "http://localhost:{$porta}");
        $this->components->twoColumnDetail('<fg=gray>Bentuk numeriknya</>', "http://127.0.0.1:{$porta}");
        $this->components->twoColumnDetail('<fg=gray>Dari perangkat lain</>', "<options=bold>{$alamat}</>");
        $this->newLine();

        if ($sebelum !== null && $sebelum !== $alamat) {
            $this->components->info("APP_URL diperbarui dari {$sebelum} menjadi {$alamat}.");
        } else {
            $this->components->info('APP_URL sudah sesuai alamat jaringan saat ini.');
        }

        $this->components->bulletList([
            'Perangkat penguji harus terhubung ke jaringan Wi-Fi yang sama.',
            'Bila tidak dapat dibuka, izinkan porta '.$porta.' pada Windows Firewall.',
            'Alamat IP dapat berganti sendiri; jalankan perintah ini lagi bila itu terjadi.',
        ]);

        $this->newLine();
        $this->line('  <fg=gray>Izinkan porta lewat PowerShell sebagai Administrator:</>');
        $this->line("  <fg=cyan>New-NetFirewallRule -DisplayName \"PANGI {$porta}\" -Direction Inbound -Protocol TCP -LocalPort {$porta} -Action Allow</>");
        $this->newLine();
    }
}
