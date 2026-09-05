<?php

namespace App\Enums;

/**
 * Arah perjalanan pada tiket: berangkat menuju lokasi, dan pulang kembali.
 */
enum ArahTiket: string
{
    case Pergi = 'pergi';

    case Pulang = 'pulang';

    public function label(): string
    {
        return match ($this) {
            self::Pergi => 'Tiket Pergi',
            self::Pulang => 'Tiket Pulang',
        };
    }

    /**
     * Keterangan rute yang diminta pada formulir.
     */
    public function keteranganRute(): string
    {
        return match ($this) {
            self::Pergi => 'Dari kota asal menuju kota tujuan perjalanan dinas.',
            self::Pulang => 'Dari kota tujuan perjalanan dinas kembali ke kota asal.',
        };
    }

    /**
     * @return list<self>
     */
    public static function urutan(): array
    {
        return [self::Pergi, self::Pulang];
    }
}
