<?php

namespace App\Enums;

/**
 * Golongan dan ruang kepegawaian beserta nama pangkatnya.
 *
 * Susunannya mengikuti daftar jenis pangkat yang berlaku: Golongan IV
 * (Pembina), III (Penata), II (Pengatur), dan I (Juru). Dipakai untuk
 * menyusun kolom "Pangkat dan Golongan" pada Surat Perjalanan Dinas.
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

    /** Nama pangkat untuk golongan ini. */
    public function pangkat(): string
    {
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
        };
    }

    /** Kelompok golongannya, misalnya "Golongan III (Penata)". */
    public function kelompok(): string
    {
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
     * "Penata Muda (III/a)".
     */
    public function lengkap(): string
    {
        return $this->pangkat().' ('.$this->value.')';
    }

    /**
     * Baca golongan dari tulisan bebas, misalnya "III/a", "iii/a",
     * atau "Penata Muda (III/a)".
     */
    public static function dari(?string $teks): ?self
    {
        if (blank($teks)) {
            return null;
        }

        if (preg_match('#\b(IV|III|II|I)\s*/\s*([a-e])\b#i', $teks, $cocok)) {
            $teks = mb_strtoupper($cocok[1]).'/'.mb_strtolower($cocok[2]);
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
