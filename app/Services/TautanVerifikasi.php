<?php

namespace App\Services;

/**
 * Menyusun alamat halaman verifikasi yang ditanam ke dalam QR code.
 *
 * Alamat sengaja dibangun dari APP_URL, bukan dari alamat yang dipakai
 * membuka halaman. Dokumen yang dicetak sambil membuka aplikasi lewat
 * localhost akan menghasilkan QR berisi "localhost", dan QR itu mustahil
 * dipindai dari ponsel karena localhost pada ponsel berarti ponsel itu
 * sendiri. QR tercetak harus menunjuk alamat yang sama bagi siapa pun
 * yang memindainya, apa pun cara dokumennya dibuat.
 */
class TautanVerifikasi
{
    public function untuk(?string $kode): ?string
    {
        if ($kode === null || $kode === '') {
            return null;
        }

        return $this->akar().route('verifikasi.tampil', $kode, false);
    }

    /**
     * Akar alamat aplikasi tanpa garis miring di ujungnya.
     */
    private function akar(): string
    {
        $akar = (string) config('app.url');

        // APP_URL yang keliru diisi beserta jalurnya pernah membuat setiap
        // tautan meleset; ambil bagian skema dan host-nya saja.
        $bagian = parse_url($akar);

        if (isset($bagian['scheme'], $bagian['host'])) {
            $akar = $bagian['scheme'].'://'.$bagian['host'];

            if (isset($bagian['port'])) {
                $akar .= ':'.$bagian['port'];
            }
        }

        return rtrim($akar, '/');
    }
}
