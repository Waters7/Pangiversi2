<?php

namespace App\Enums;

/**
 * Cara sebuah komponen rincian biaya sampai ke pelaksana.
 *
 * Uang harian tidak memakai pilihan ini: ia selalu dibagi 80% di muka dan
 * 20% saat pelunasan. Komponen lain dibayarkan seutuhnya lewat uang muka,
 * atau — bila pelaksana sudah membayarnya lebih dahulu dengan uangnya
 * sendiri — diganti saat pelunasan, sehingga masuk sisa bayar.
 */
enum CaraBayarBiaya: string
{
    case UangMuka = 'uang_muka';

    case Penggantian = 'penggantian';

    public function label(): string
    {
        return match ($this) {
            self::UangMuka => 'Dibayarkan lewat uang muka',
            self::Penggantian => 'Dibayar pelaksana dahulu — diganti saat pelunasan',
        };
    }

    public function labelSingkat(): string
    {
        return match ($this) {
            self::UangMuka => 'Uang muka',
            self::Penggantian => 'Penggantian',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::UangMuka => 'bg-amber-100 text-amber-800',
            self::Penggantian => 'bg-blue-100 text-blue-800',
        };
    }
}
