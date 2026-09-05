<?php

namespace App\Enums;

/**
 * Empat ruas transportasi lokal yang dinotakan pelaksana, dari berangkat
 * meninggalkan rumah sampai kembali lagi ke rumah.
 *
 * Ruasnya tetap dan berurutan supaya tim keuangan memeriksa hal yang sama
 * pada setiap berkas, dan tidak ada ruas yang diam-diam terlewat.
 */
enum RuasTransport: int
{
    case RumahKeBandara = 1;

    case BandaraKeLokasi = 2;

    case LokasiKeBandara = 3;

    case BandaraKeRumah = 4;

    public function label(): string
    {
        return match ($this) {
            self::RumahKeBandara => 'Rumah ke bandara',
            self::BandaraKeLokasi => 'Bandara ke lokasi tujuan perjadin',
            self::LokasiKeBandara => 'Lokasi tujuan perjadin ke bandara',
            self::BandaraKeRumah => 'Bandara ke rumah pelaksana',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::RumahKeBandara => 'Keberangkatan dari tempat kedudukan.',
            self::BandaraKeLokasi => 'Setibanya di kota tujuan.',
            self::LokasiKeBandara => 'Saat hendak kembali.',
            self::BandaraKeRumah => 'Sampai kembali di tempat kedudukan.',
        };
    }

    /**
     * @return list<self>
     */
    public static function urutan(): array
    {
        return self::cases();
    }

    public static function dari(int|string|null $nilai): ?self
    {
        return self::tryFrom((int) $nilai);
    }
}
