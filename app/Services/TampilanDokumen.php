<?php

namespace App\Services;

use App\Enums\DokumenCetak;

/**
 * Pengaturan satu dokumen cetak dalam bentuk yang enak dibaca dari Blade:
 * `$dok->tampil('kop')` dan `$dok->teks('judul')`.
 */
class TampilanDokumen
{
    /**
     * @param  array{kertas: string, huruf: float, lebar_kop: int, elemen: array<string, bool>, teks: array<string, string>}  $isi
     */
    public function __construct(public readonly DokumenCetak $dokumen, private array $isi) {}

    public function tampil(string $elemen): bool
    {
        return (bool) ($this->isi['elemen'][$elemen] ?? true);
    }

    /**
     * Teks yang sudah diganti penandanya, mis. :tahun dan :nomor.
     *
     * @param  array<string, string|int|null>  $ganti
     */
    public function teks(string $kunci, array $ganti = []): string
    {
        $teks = $this->isi['teks'][$kunci] ?? '';

        foreach ($ganti as $penanda => $nilai) {
            $teks = str_replace(':'.$penanda, (string) $nilai, $teks);
        }

        return $teks;
    }

    public function kertas(): string
    {
        return $this->isi['kertas'];
    }

    public function huruf(): float
    {
        return $this->isi['huruf'];
    }

    public function lebarKop(): int
    {
        return $this->isi['lebar_kop'];
    }

    /** @return array<string, mixed> */
    public function isi(): array
    {
        return $this->isi;
    }
}
