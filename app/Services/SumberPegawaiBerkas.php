<?php

namespace App\Services;

use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Sumber data pegawai dari berkas CSV yang tersimpan di dalam proyek —
 * dipakai seeder, sedangkan unggahan Tim SDM memakai SumberPegawaiCsv.
 */
class SumberPegawaiBerkas implements SumberDataPegawai
{
    public function __construct(private string $jalur) {}

    public function nama(): string
    {
        return 'Berkas '.basename($this->jalur);
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    public function ambil(): Collection
    {
        if (! is_readable($this->jalur)) {
            throw new RuntimeException("Berkas pegawai tidak ditemukan: {$this->jalur}");
        }

        $baris = collect();
        $handle = fopen($this->jalur, 'r');
        $header = null;

        while (($kolom = fgetcsv($handle, escape: '\\')) !== false) {
            if ($kolom === [null] || $kolom === []) {
                continue;
            }

            if ($header === null) {
                // Buang BOM yang ditulis agar Excel membaca huruf beraksen.
                $kolom[0] = preg_replace('/^\x{FEFF}/u', '', (string) $kolom[0]);
                $header = array_map(
                    fn (?string $nama) => str_replace(' ', '_', mb_strtolower(trim((string) $nama))),
                    $kolom
                );

                continue;
            }

            $isi = [];
            foreach (SumberPegawaiCsv::KOLOM as $nama) {
                $posisi = array_search($nama, $header, true);
                $nilai = $posisi === false ? null : trim((string) ($kolom[$posisi] ?? ''));
                $isi[$nama] = $nilai === '' ? null : $nilai;
            }

            $baris->push($isi);
        }

        fclose($handle);

        return $baris;
    }
}
