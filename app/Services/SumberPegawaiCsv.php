<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Sumber data pegawai dari berkas CSV yang diunggah Tim SDM.
 */
class SumberPegawaiCsv implements SumberDataPegawai
{
    /**
     * Kolom yang dikenali importer, sekaligus urutan header saat ekspor.
     *
     * @var list<string>
     */
    public const KOLOM = [
        'nama',
        'nip',
        'email',
        'no_hp',
        'role',
        'jabatan',
        'golongan',
        'unit_kode',
        'atasan_nip',
        'nama_bank',
        'nomor_rekening',
        'nama_rekening',
        'password',
    ];

    public function __construct(private UploadedFile $berkas) {}

    public function nama(): string
    {
        return 'Berkas CSV '.$this->berkas->getClientOriginalName();
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    public function ambil(): Collection
    {
        $baris = collect();
        $handle = fopen($this->berkas->getRealPath(), 'r');

        if ($handle === false) {
            return $baris;
        }

        $header = null;

        while (($kolom = fgetcsv($handle, escape: '\\')) !== false) {
            // Lewati baris kosong yang kerap tertinggal di akhir berkas.
            if ($kolom === [null] || $kolom === []) {
                continue;
            }

            if ($header === null) {
                $header = $this->normalkanHeader($kolom);

                continue;
            }

            $baris->push($this->petakanBaris($header, $kolom));
        }

        fclose($handle);

        return $baris;
    }

    /**
     * Samakan penulisan header agar toleran terhadap spasi dan huruf besar.
     *
     * @param  list<string|null>  $kolom
     * @return list<string>
     */
    private function normalkanHeader(array $kolom): array
    {
        return array_map(
            fn (?string $nama) => str_replace(' ', '_', mb_strtolower(trim((string) $nama))),
            $kolom,
        );
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $kolom
     * @return array<string, string|null>
     */
    private function petakanBaris(array $header, array $kolom): array
    {
        $baris = [];

        foreach (self::KOLOM as $nama) {
            $posisi = array_search($nama, $header, true);

            $nilai = $posisi === false ? null : trim((string) ($kolom[$posisi] ?? ''));

            $baris[$nama] = $nilai === '' ? null : $nilai;
        }

        return $baris;
    }
}
