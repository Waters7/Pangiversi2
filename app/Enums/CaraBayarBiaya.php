<?php

namespace App\Enums;

use App\Models\Keuangan;

/**
 * Cara sebuah komponen rincian biaya sampai ke pelaksana, dikonfirmasi tim
 * keuangan per komponen.
 *
 * Komponen dapat dibayarkan penuh lewat uang muka, baru dibayarkan 80% di
 * muka dengan 20% sisanya saat pelunasan, atau — bila pelaksana sudah
 * membayarnya lebih dahulu dengan uangnya sendiri — diganti seluruhnya saat
 * pelunasan. Bawaan uang harian adalah 80%, komponen lain dibayarkan penuh.
 */
enum CaraBayarBiaya: string
{
    case UangMuka = 'uang_muka';

    case Bertahap = 'bertahap';

    case Penggantian = 'penggantian';

    public function label(): string
    {
        return match ($this) {
            self::UangMuka => 'Sudah dibayarkan — penuh lewat uang muka',
            self::Bertahap => 'Baru dibayarkan 80% — 20% sisanya saat pelunasan',
            self::Penggantian => 'Dibayar pelaksana dahulu — diganti saat pelunasan',
        };
    }

    public function labelSingkat(): string
    {
        return match ($this) {
            self::UangMuka => 'Sudah dibayarkan',
            self::Bertahap => 'Baru dibayarkan 80%',
            self::Penggantian => 'Penggantian',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::UangMuka => 'bg-emerald-100 text-emerald-800',
            self::Bertahap => 'bg-amber-100 text-amber-800',
            self::Penggantian => 'bg-blue-100 text-blue-800',
        };
    }

    /**
     * Bagian nominal yang dibayarkan lewat uang muka; sisanya saat pelunasan.
     */
    public function porsiDiMuka(): float
    {
        return match ($this) {
            self::UangMuka => 1.0,
            self::Bertahap => Keuangan::PORSI_UANG_HARIAN_DI_MUKA,
            self::Penggantian => 0.0,
        };
    }
}
