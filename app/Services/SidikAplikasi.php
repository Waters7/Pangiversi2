<?php

namespace App\Services;

/**
 * Sidik keaslian kode sumber PANGI.
 *
 * Isinya terenkripsi supaya tidak terbaca begitu saja dan tidak pernah
 * ditampilkan di aplikasi. Dibuka lewat perintah tersembunyi
 * `php artisan pangi:sidik` bila keaslian kodenya perlu dibuktikan.
 */
final class SidikAplikasi
{
    private const KUNCI = 'T7HpklCpmyOzeBULymjNUJARHuSzKugOqwvwsa0guUQ=';

    /** Vektor awal (12 bita), tag (16 bita), lalu sandinya. */
    private const SIDIK = 'RlZs+lQEZHuEmIGD8nNB03mx+HxU+zg3+INlPK8IwfawVscpKvg6nV/5M0JBlMIl';

    /**
     * Isi sidik yang sudah dibuka, atau string kosong bila sandinya telah
     * diubah sehingga tidak lagi cocok dengan tagnya.
     */
    public function teks(): string
    {
        $sidik = (string) base64_decode(self::SIDIK, true);

        $teks = openssl_decrypt(
            substr($sidik, 28),
            'aes-256-gcm',
            (string) base64_decode(self::KUNCI, true),
            OPENSSL_RAW_DATA,
            substr($sidik, 0, 12),
            substr($sidik, 12, 16),
        );

        return $teks === false ? '' : $teks;
    }

    /**
     * SHA-256 isinya — untuk mencocokkan sidik tanpa menuliskan isinya.
     */
    public function hash(): string
    {
        return hash('sha256', $this->teks());
    }
}
