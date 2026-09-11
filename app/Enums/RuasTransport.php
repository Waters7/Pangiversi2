<?php

namespace App\Enums;

/**
 * Ruas transportasi lokal yang dinotakan pelaksana.
 *
 * Luar kota menempuh empat ruas tetap, dari berangkat meninggalkan rumah
 * sampai kembali lagi ke rumah — berurutan supaya tim keuangan memeriksa
 * hal yang sama pada setiap berkas, dan tidak ada ruas yang diam-diam
 * terlewat. Dalam kota tidak melewati bandara sama sekali, jadi hanya ada
 * satu ruas: biaya transport lokalnya.
 */
enum RuasTransport: int
{
    case RumahKeBandara = 1;

    case BandaraKeLokasi = 2;

    case LokasiKeBandara = 3;

    case BandaraKeRumah = 4;

    case Lokal = 5;

    public function label(): string
    {
        return match ($this) {
            self::RumahKeBandara => 'Rumah ke bandara',
            self::BandaraKeLokasi => 'Bandara ke lokasi tujuan perjadin',
            self::LokasiKeBandara => 'Lokasi tujuan perjadin ke bandara',
            self::BandaraKeRumah => 'Bandara ke rumah pelaksana',
            self::Lokal => 'Transport lokal',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::RumahKeBandara => 'Keberangkatan dari tempat kedudukan.',
            self::BandaraKeLokasi => 'Setibanya di kota tujuan.',
            self::LokasiKeBandara => 'Saat hendak kembali.',
            self::BandaraKeRumah => 'Sampai kembali di tempat kedudukan.',
            self::Lokal => 'Seluruh biaya transportasi selama perjalanan dinas dalam kota.',
        };
    }

    public function dalamKota(): bool
    {
        return $this === self::Lokal;
    }

    /**
     * Empat ruas perjalanan luar kota, berurutan.
     *
     * @return list<self>
     */
    public static function urutan(): array
    {
        return [self::RumahKeBandara, self::BandaraKeLokasi, self::LokasiKeBandara, self::BandaraKeRumah];
    }

    /**
     * Ruas yang berlaku menurut wilayah perjalanannya.
     *
     * @return list<self>
     */
    public static function untuk(bool $dalamKota): array
    {
        return $dalamKota ? [self::Lokal] : self::urutan();
    }

    public static function dari(int|string|null $nilai): ?self
    {
        return self::tryFrom((int) $nilai);
    }
}
