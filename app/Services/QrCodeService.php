<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Menghasilkan QR code sebagai data URI agar dapat langsung ditanam ke dalam
 * PDF yang dirender dompdf tanpa perlu menulis berkas sementara.
 */
class QrCodeService
{
    /**
     * @param  int  $ukuran  Sisi QR dalam piksel.
     */
    public function dataUri(string $isi, int $ukuran = 220): string
    {
        $hasil = new Builder(
            writer: new PngWriter,
            data: $isi,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $ukuran,
            margin: 8,
        )->build();

        return $hasil->getDataUri();
    }
}
