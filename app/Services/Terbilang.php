<?php

namespace App\Services;

/**
 * Mengubah bilangan menjadi kata dalam bahasa Indonesia, dipakai pada baris
 * "Terbilang" dokumen rincian biaya perjalanan dinas.
 */
class Terbilang
{
    /**
     * @var list<string>
     */
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    public function konversi(float|int $angka): string
    {
        $angka = (int) round(abs($angka));

        if ($angka === 0) {
            return 'nol rupiah';
        }

        // Rekursi menyisakan spasi ganda pada bilangan bulat seperti 250.000.
        $kata = preg_replace('/\s+/', ' ', $this->eja($angka));

        return trim((string) $kata).' rupiah';
    }

    private function eja(int $angka): string
    {
        return match (true) {
            $angka < 12 => ' '.self::SATUAN[$angka],
            $angka < 20 => $this->eja($angka - 10).' belas',
            $angka < 100 => $this->eja(intdiv($angka, 10)).' puluh'.$this->eja($angka % 10),
            $angka < 200 => ' seratus'.$this->eja($angka - 100),
            $angka < 1_000 => $this->eja(intdiv($angka, 100)).' ratus'.$this->eja($angka % 100),
            $angka < 2_000 => ' seribu'.$this->eja($angka - 1_000),
            $angka < 1_000_000 => $this->eja(intdiv($angka, 1_000)).' ribu'.$this->eja($angka % 1_000),
            $angka < 1_000_000_000 => $this->eja(intdiv($angka, 1_000_000)).' juta'.$this->eja($angka % 1_000_000),
            $angka < 1_000_000_000_000 => $this->eja(intdiv($angka, 1_000_000_000)).' miliar'.$this->eja($angka % 1_000_000_000),
            default => $this->eja(intdiv($angka, 1_000_000_000_000)).' triliun'.$this->eja($angka % 1_000_000_000_000),
        };
    }
}
