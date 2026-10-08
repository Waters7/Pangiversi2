<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim pesan WhatsApp otomatis lewat layanan gateway — dipakai
 * pemantau server untuk peringatan saat aplikasi tidak dapat diakses.
 *
 * Gateway yang didukung adalah yang lazim dipakai instansi: Fonnte (satu
 * alamat untuk semua akun) dan Wablas (alamat peladen per akun). Keduanya
 * cukup dengan token dari dasbor layanannya.
 */
class PengirimWhatsapp
{
    /** @var array<string, string> */
    public const GATEWAY = [
        'fonnte' => 'Fonnte',
        'wablas' => 'Wablas',
    ];

    private const URL_FONNTE = 'https://api.fonnte.com/send';

    private const TENGGAT_DETIK = 20;

    /**
     * @param  array{gateway: string, token: string, url_wablas: string}  $sambungan
     * @param  list<string>  $nomor  Nomor internasional tanpa tanda baca.
     * @return array{terkirim: bool, pesan: string}
     */
    public function kirim(array $sambungan, array $nomor, string $isi): array
    {
        if ($sambungan['token'] === '') {
            return ['terkirim' => false, 'pesan' => 'Token gateway WhatsApp belum diatur.'];
        }

        if ($nomor === []) {
            return ['terkirim' => false, 'pesan' => 'Nomor WhatsApp penerima belum diatur.'];
        }

        try {
            return $sambungan['gateway'] === 'wablas'
                ? $this->lewatWablas($sambungan['url_wablas'], $sambungan['token'], $nomor, $isi)
                : $this->lewatFonnte($sambungan['token'], $nomor, $isi);
        } catch (Throwable $e) {
            return ['terkirim' => false, 'pesan' => 'Gateway tidak dapat dihubungi: '.Str::limit($e->getMessage(), 160)];
        }
    }

    /**
     * @param  list<string>  $nomor
     * @return array{terkirim: bool, pesan: string}
     */
    private function lewatFonnte(string $token, array $nomor, string $isi): array
    {
        $balasan = Http::timeout(self::TENGGAT_DETIK)
            ->asForm()
            ->withHeaders(['Authorization' => $token])
            ->post(self::URL_FONNTE, [
                'target' => implode(',', $nomor),
                'message' => $isi,
                'countryCode' => '62',
            ]);

        if ($balasan->successful() && $balasan->json('status') === true) {
            return ['terkirim' => true, 'pesan' => 'Terkirim lewat Fonnte ke '.count($nomor).' nomor.'];
        }

        return ['terkirim' => false, 'pesan' => 'Fonnte menolak: '.($balasan->json('reason') ?? $balasan->json('detail') ?? 'HTTP '.$balasan->status())];
    }

    /**
     * Wablas dikirimi satu permintaan per nomor, supaya nomor yang ditolak
     * tidak menggagalkan nomor lainnya.
     *
     * @param  list<string>  $nomor
     * @return array{terkirim: bool, pesan: string}
     */
    private function lewatWablas(string $url, string $token, array $nomor, string $isi): array
    {
        if ($url === '') {
            return ['terkirim' => false, 'pesan' => 'Alamat peladen Wablas belum diatur.'];
        }

        $ditolak = [];

        foreach ($nomor as $satu) {
            $balasan = Http::timeout(self::TENGGAT_DETIK)
                ->asForm()
                ->withHeaders(['Authorization' => $token])
                ->post(rtrim($url, '/').'/api/send-message', [
                    'phone' => $satu,
                    'message' => $isi,
                ]);

            if (! ($balasan->successful() && $balasan->json('status') === true)) {
                $ditolak[] = $satu.' ('.($balasan->json('message') ?? 'HTTP '.$balasan->status()).')';
            }
        }

        if ($ditolak === []) {
            return ['terkirim' => true, 'pesan' => 'Terkirim lewat Wablas ke '.count($nomor).' nomor.'];
        }

        return [
            'terkirim' => count($ditolak) < count($nomor),
            'pesan' => 'Wablas menolak '.implode(', ', $ditolak),
        ];
    }

    /**
     * Nomor WhatsApp dalam format internasional tanpa tanda baca,
     * misalnya 0812-3456 menjadi 628123456.
     */
    public static function normalkan(?string $nomor): ?string
    {
        $angka = preg_replace('/\D/', '', (string) $nomor);

        if (blank($angka)) {
            return null;
        }

        return match (true) {
            str_starts_with($angka, '62') => $angka,
            str_starts_with($angka, '0') => '62'.mb_substr($angka, 1),
            default => '62'.$angka,
        };
    }

    /**
     * Daftar nomor dari isian bebas — dipisah koma, titik koma, atau baris.
     * Spasi bukan pemisah: nomor lazim ditulis "0812 3456 7890".
     *
     * @return list<string>
     */
    public static function daftarNomor(?string $teks): array
    {
        return collect(preg_split('/[\r\n,;]+/', (string) $teks) ?: [])
            ->map(fn (string $nomor) => self::normalkan($nomor))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
