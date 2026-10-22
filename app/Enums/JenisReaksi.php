<?php

namespace App\Enums;

/**
 * Reaksi yang dapat diberikan pada kartu news feed dashboard eksekutif.
 *
 * Satu pengguna satu reaksi per kartu: memilih reaksi lain menggantinya,
 * memilih yang sama mencabutnya.
 */
enum JenisReaksi: string
{
    case Suka = 'suka';
    case Apresiasi = 'apresiasi';
    case Semangat = 'semangat';
    case Doakan = 'doakan';
    case Selamat = 'selamat';

    public function emoji(): string
    {
        return match ($this) {
            self::Suka => '👍',
            self::Apresiasi => '👏',
            self::Semangat => '💪',
            self::Doakan => '🙏',
            self::Selamat => '🎉',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Suka => 'Suka',
            self::Apresiasi => 'Apresiasi',
            self::Semangat => 'Semangat',
            self::Doakan => 'Doakan',
            self::Selamat => 'Selamat',
        };
    }
}
