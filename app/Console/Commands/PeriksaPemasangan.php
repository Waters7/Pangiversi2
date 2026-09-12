<?php

namespace App\Console\Commands;

use App\Services\KertasCetak;
use App\Services\QrCodeService;
use App\Services\VersiAplikasi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Throwable;

/**
 * Memeriksa kesiapan pemasangan di peladen: versi PHP dan ekstensinya,
 * pembuatan QR dan PDF, tautan storage, izin tulis, serta pengaturan .env
 * yang lazim terlewat saat pindah peladen.
 *
 * Dibuat untuk hosting bersama, tempat exec() dimatikan dan tidak ada cara
 * lain memastikan GD atau dompdf berjalan selain benar-benar mencobanya.
 */
class PeriksaPemasangan extends Command
{
    protected $signature = 'pangi:periksa';

    protected $description = 'Periksa kesiapan pemasangan: PHP, ekstensi, QR, PDF, storage, dan pengaturan .env';

    private bool $gagal = false;

    public function handle(VersiAplikasi $versi, QrCodeService $qr): int
    {
        $this->components->info('PANGI '.$versi->label().' — pemeriksaan pemasangan');

        $this->periksa('PHP '.PHP_VERSION, version_compare(PHP_VERSION, '8.4.0', '>='), 'butuh 8.4 ke atas');

        foreach (['pdo_mysql', 'mbstring', 'dom', 'xml', 'fileinfo', 'ctype', 'tokenizer', 'openssl', 'bcmath'] as $ekstensi) {
            $this->periksa("Ekstensi {$ekstensi}", extension_loaded($ekstensi));
        }

        $this->periksa(
            'Ekstensi gd / imagick (QR dan gambar PDF)',
            extension_loaded('gd') || extension_loaded('imagick'),
        );

        $this->periksa('Ekstensi zip (ekspor Excel)', extension_loaded('zip'), 'peringatan', kritis: false);

        $this->periksaQr($qr);
        $this->periksaPdf();
        $this->periksaStorage();
        $this->periksaPengaturan();

        $this->newLine();

        if ($this->gagal) {
            $this->components->error('Ada pemeriksaan yang gagal — lihat baris bertanda GAGAL di atas.');

            return self::FAILURE;
        }

        $this->components->info('Seluruh pemeriksaan lolos.');

        return self::SUCCESS;
    }

    private function periksaQr(QrCodeService $qr): void
    {
        try {
            $data = $qr->dataUri(config('app.url').'/verifikasi/UJI-PEMASANGAN', 120);
            $png = base64_decode(substr($data, strlen('data:image/png;base64,')), true);

            $this->periksa(
                'QR terbentuk (PNG '.strlen((string) $png).' byte)',
                str_starts_with($data, 'data:image/png;base64,') && str_starts_with((string) $png, "\x89PNG"),
            );
        } catch (Throwable $e) {
            $this->periksa('QR terbentuk', false, $e->getMessage());
        }
    }

    private function periksaPdf(): void
    {
        try {
            $isi = Pdf::loadHTML('<p>Uji pemasangan PANGI</p>')
                ->setPaper(KertasCetak::UKURAN)
                ->output();

            $this->periksa(
                'PDF folio terbentuk ('.strlen($isi).' byte)',
                str_starts_with($isi, '%PDF') && str_contains($isi, '612.000 936.000'),
            );
        } catch (Throwable $e) {
            $this->periksa('PDF terbentuk', false, $e->getMessage());
        }
    }

    private function periksaStorage(): void
    {
        $tautan = public_path('storage');
        $tujuan = storage_path('app/public');

        $this->periksa(
            'public/storage menunjuk storage/app/public',
            file_exists($tautan) && realpath($tautan) === realpath($tujuan),
            'jalankan: ln -s ../storage/app/public public/storage',
        );

        foreach (['storage/app/public', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $folder) {
            $this->periksa("{$folder} dapat ditulis", is_writable(base_path($folder)));
        }
    }

    private function periksaPengaturan(): void
    {
        $this->periksa('APP_KEY terisi', filled(config('app.key')));

        $produksi = app()->isProduction();

        $this->periksa(
            'APP_URL memakai https ('.config('app.url').')',
            ! $produksi || str_starts_with((string) config('app.url'), 'https://'),
            'QR dibangun dari alamat ini',
            kritis: $produksi,
        );

        $this->periksa(
            'APP_DEBUG mati di produksi',
            ! $produksi || ! config('app.debug'),
            kritis: $produksi,
        );

        $this->periksa(
            'Zona waktu Asia/Makassar (WITA)',
            config('app.timezone') === 'Asia/Makassar',
            'setel APP_TIMEZONE=Asia/Makassar',
            kritis: false,
        );

        $this->periksa('Bahasa id', config('app.locale') === 'id', kritis: false);
    }

    private function periksa(string $nama, bool $lolos, ?string $catatan = null, bool $kritis = true): void
    {
        if ($lolos) {
            $this->components->twoColumnDetail($nama, '<fg=green>OK</>');

            return;
        }

        if ($kritis) {
            $this->gagal = true;
        }

        $status = $kritis ? '<fg=red>GAGAL</>' : '<fg=yellow>PERINGATAN</>';

        $this->components->twoColumnDetail($nama.($catatan ? " <fg=gray>— {$catatan}</>" : ''), $status);
    }
}
