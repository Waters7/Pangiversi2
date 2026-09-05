<?php

namespace App\Enums;

/**
 * Kategori perincian biaya perjalanan dinas mengikuti Lampiran II
 * PMK 113/PMK.05/2012, agar rincian yang dicetak sesuai format resmi.
 */
enum KategoriBiaya: string
{
    case Transport = 'transport';

    case UangHarian = 'uang_harian';

    case TransportLokal = 'transport_lokal';

    case Penginapan = 'penginapan';

    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Transport => 'Transport',
            self::UangHarian => 'Uang Harian',
            self::TransportLokal => 'Transport Lokal',
            self::Penginapan => 'Uang Penginapan',
            self::Lainnya => 'Biaya Lainnya',
        };
    }

    /**
     * Urutan penomoran pada dokumen rincian resmi.
     */
    public function urutan(): int
    {
        return match ($this) {
            self::Transport => 1,
            self::UangHarian => 2,
            self::TransportLokal => 3,
            self::Penginapan => 4,
            self::Lainnya => 5,
        };
    }

    /**
     * Biaya yang wajib didukung nota agar dapat diganti tim keuangan.
     */
    public function butuhNota(): bool
    {
        return in_array($this, [self::Transport, self::TransportLokal], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Transport => 'bg-blue-100 text-blue-700',
            self::UangHarian => 'bg-teal-100 text-teal-700',
            self::TransportLokal => 'bg-indigo-100 text-indigo-700',
            self::Penginapan => 'bg-amber-100 text-amber-700',
            self::Lainnya => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::urutanCetak() as $kategori) {
            $options[$kategori->value] = $kategori->label();
        }

        return $options;
    }

    /**
     * @return list<self>
     */
    public static function urutanCetak(): array
    {
        $kategori = self::cases();

        usort($kategori, fn (self $a, self $b) => $a->urutan() <=> $b->urutan());

        return $kategori;
    }

    public static function dari(?string $nilai): self
    {
        return self::tryFrom((string) $nilai) ?? self::Lainnya;
    }
}
