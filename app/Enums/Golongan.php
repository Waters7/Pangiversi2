<?php

namespace App\Enums;

/**
 * Golongan dan ruang kepegawaian beserta nama pangkatnya.
 *
 * Susunannya mengikuti daftar jenis pangkat yang berlaku: Golongan IV
 * (Pembina), III (Penata), II (Pengatur), dan I (Juru), ditambah golongan
 * Pegawai Pemerintah dengan Perjanjian Kerja (PPPK) I sampai XVII — yang
 * tidak berpangkat dan tidak beruang. Dipakai untuk menyusun kolom
 * "Pangkat dan Golongan" pada Surat Perjalanan Dinas.
 */
enum Golongan: string
{
    case IVe = 'IV/e';
    case IVd = 'IV/d';
    case IVc = 'IV/c';
    case IVb = 'IV/b';
    case IVa = 'IV/a';

    case IIId = 'III/d';
    case IIIc = 'III/c';
    case IIIb = 'III/b';
    case IIIa = 'III/a';

    case IId = 'II/d';
    case IIc = 'II/c';
    case IIb = 'II/b';
    case IIa = 'II/a';

    case Id = 'I/d';
    case Ic = 'I/c';
    case Ib = 'I/b';
    case Ia = 'I/a';

    // Golongan PPPK: angka romawi saja, tanpa ruang.
    case PppkXVII = 'XVII';
    case PppkXVI = 'XVI';
    case PppkXV = 'XV';
    case PppkXIV = 'XIV';
    case PppkXIII = 'XIII';
    case PppkXII = 'XII';
    case PppkXI = 'XI';
    case PppkX = 'X';
    case PppkIX = 'IX';
    case PppkVIII = 'VIII';
    case PppkVII = 'VII';
    case PppkVI = 'VI';
    case PppkV = 'V';
    case PppkIV = 'IV';
    case PppkIII = 'III';
    case PppkII = 'II';
    case PppkI = 'I';

    /** Golongan Pegawai Pemerintah dengan Perjanjian Kerja. */
    public function pppk(): bool
    {
        return ! str_contains($this->value, '/');
    }

    /** Nama pangkat untuk golongan ini; PPPK tidak berpangkat. */
    public function pangkat(): string
    {
        if ($this->pppk()) {
            return 'PPPK';
        }

        return match ($this) {
            self::IVe => 'Pembina Utama',
            self::IVd => 'Pembina Utama Madya',
            self::IVc => 'Pembina Utama Muda',
            self::IVb => 'Pembina Tingkat I',
            self::IVa => 'Pembina',

            self::IIId => 'Penata Tingkat I',
            self::IIIc => 'Penata',
            self::IIIb => 'Penata Muda Tingkat I',
            self::IIIa => 'Penata Muda',

            self::IId => 'Pengatur Tingkat I',
            self::IIc => 'Pengatur',
            self::IIb => 'Pengatur Muda Tingkat I',
            self::IIa => 'Pengatur Muda',

            self::Id => 'Juru Tingkat I',
            self::Ic => 'Juru',
            self::Ib => 'Juru Muda Tingkat I',
            self::Ia => 'Juru Muda',
            default => 'PPPK',
        };
    }

    /** Kelompok golongannya, misalnya "Golongan III (Penata)". */
    public function kelompok(): string
    {
        if ($this->pppk()) {
            return 'PPPK (Golongan I–XVII)';
        }

        return match ($this->angka()) {
            'IV' => 'Golongan IV (Pembina)',
            'III' => 'Golongan III (Penata)',
            'II' => 'Golongan II (Pengatur)',
            default => 'Golongan I (Juru)',
        };
    }

    /** Angka romawi golongannya saja. */
    public function angka(): string
    {
        return strstr($this->value, '/', true) ?: $this->value;
    }

    /** Huruf ruangnya saja. */
    public function ruang(): string
    {
        return ltrim(strstr($this->value, '/') ?: '', '/');
    }

    /**
     * Tulisan lengkap seperti yang tercetak pada SPD:
     * "Penata Muda (III/a)", atau "Golongan IX (PPPK)" bagi PPPK.
     */
    public function lengkap(): string
    {
        return $this->pppk()
            ? 'Golongan '.$this->value.' (PPPK)'
            : $this->pangkat().' ('.$this->value.')';
    }

    /**
     * Baca golongan dari tulisan bebas, misalnya "III/a", "iii/a",
     * "Penata Muda (III/a)", atau — bagi PPPK — "IX", "Golongan IX (PPPK)".
     */
    public static function dari(?string $teks): ?self
    {
        if (blank($teks)) {
            return null;
        }

        if (preg_match('#\b(IV|III|II|I)\s*/\s*([a-e])\b#i', $teks, $cocok)) {
            return self::tryFrom(mb_strtoupper($cocok[1]).'/'.mb_strtolower($cocok[2]));
        }

        // Tanpa ruang berarti golongan PPPK; angka romawi terpanjang dicoba
        // lebih dulu. Tulisan yang memuat garis miring tetap golongan PNS.
        if (! str_contains($teks, '/') && preg_match('#\b(XVII|XVI|XV|XIV|XIII|XII|XI|IX|X|VIII|VII|VI|IV|V|III|II|I)\b#i', $teks, $cocok)) {
            return self::tryFrom(mb_strtoupper($cocok[1]));
        }

        return self::tryFrom(trim($teks));
    }

    /**
     * Pilihan untuk kotak pilih, dikelompokkan menurut golongan.
     *
     * @return array<string, array<string, string>>
     */
    public static function terkelompok(): array
    {
        $hasil = [];

        foreach (self::cases() as $golongan) {
            $hasil[$golongan->kelompok()][$golongan->value] = $golongan->lengkap();
        }

        return $hasil;
    }
}
