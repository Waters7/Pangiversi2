<?php

namespace App\Enums;

/**
 * Isian nominal pada berkas pertanggungjawaban pelaksana yang dapat juga
 * ditulis tim keuangan pada rincian biaya.
 *
 * Satu komponen hanya boleh berasal dari satu pihak: bila tim keuangan
 * sudah menetapkan nominalnya, isian pelaksana terkunci pada angka itu;
 * bila pelaksana sudah mengisinya beserta bukti, tim keuangan tidak dapat
 * menambah baris untuk komponen yang sama. Tanpa aturan ini satu tiket atau
 * satu bill hotel dapat tercatat dua kali pada rincian biaya.
 *
 * Nilainya sama dengan kunci_sumber baris yang lahir dari berkas pelaksana.
 */
enum IsianBiaya: string
{
    case TiketPergi = 'tiket:pergi';

    case TiketPulang = 'tiket:pulang';

    /** Satu baris tim keuangan untuk tiket pergi sekaligus pulang. */
    case TiketPulangPergi = 'tiket:pp';

    /**
     * Baris transport tim keuangan yang memang bukan tiket pelaksana —
     * dinyatakan tegas, supaya tidak tertukar dengan baris lama yang belum
     * pernah ditentukan tiketnya.
     */
    case BukanTiket = 'transport:lain';

    case Penginapan = 'hotel';

    case Penyelenggaraan = 'penyelenggaraan';

    public function label(): string
    {
        return match ($this) {
            self::TiketPergi => 'Tiket Pergi',
            self::TiketPulang => 'Tiket Pulang',
            self::TiketPulangPergi => 'Tiket Pergi & Pulang (PP)',
            self::BukanTiket => 'Transport lain, bukan tiket pelaksana',
            self::Penginapan => 'Bill Hotel / Uang Penginapan',
            self::Penyelenggaraan => 'Biaya Penyelenggaraan',
        };
    }

    /**
     * Isian pelaksana yang terwakili oleh nilai ini.
     *
     * @return list<self>
     */
    public function mencakup(): array
    {
        return match ($this) {
            self::TiketPulangPergi => [self::TiketPergi, self::TiketPulang],
            self::BukanTiket => [],
            default => [$this],
        };
    }

    /**
     * Isian yang benar-benar ada pada formulir berkas pelaksana.
     *
     * @return list<self>
     */
    public static function isianPelaksana(): array
    {
        return [self::TiketPergi, self::TiketPulang, self::Penginapan, self::Penyelenggaraan];
    }

    /**
     * Pilihan bagi baris transport tim keuangan.
     *
     * @return list<self>
     */
    public static function pilihanTransport(): array
    {
        return [self::TiketPergi, self::TiketPulang, self::TiketPulangPergi, self::BukanTiket];
    }

    public static function dariArah(ArahTiket $arah): self
    {
        return $arah === ArahTiket::Pergi ? self::TiketPergi : self::TiketPulang;
    }

    /**
     * Isian pelaksana yang diwakili baris tulisan tim keuangan.
     *
     * Baris transport menyebut sendiri tiket mana yang diwakilinya — atau
     * bukan tiket sama sekali. Penginapan dan biaya penyelenggaraan hanya
     * punya satu isian pada berkas pelaksana, jadi langsung terwakili.
     */
    public static function untukBarisKeuangan(KategoriBiaya $kategori, ?string $pilihanTransport): ?self
    {
        return match ($kategori) {
            KategoriBiaya::Transport => in_array($pilihanTransport, array_column(self::pilihanTransport(), 'value'), true)
                ? self::from($pilihanTransport)
                : null,
            KategoriBiaya::Penginapan => self::Penginapan,
            KategoriBiaya::Penyelenggaraan => self::Penyelenggaraan,
            default => null,
        };
    }
}
