<?php

namespace App\Enums;

/**
 * Perkembangan sebuah rencana tindak lanjut hasil perjalanan dinas.
 */
enum StatusTindakLanjut: string
{
    case Rencana = 'rencana';

    case Berjalan = 'berjalan';

    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Rencana => 'Rencana',
            self::Berjalan => 'Sedang Berjalan',
            self::Selesai => 'Selesai',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Rencana => 'bg-slate-100 text-slate-600',
            self::Berjalan => 'bg-blue-100 text-blue-700',
            self::Selesai => 'bg-emerald-100 text-emerald-700',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $pilihan = [];

        foreach (self::cases() as $status) {
            $pilihan[$status->value] = $status->label();
        }

        return $pilihan;
    }

    public static function dari(?string $nilai): self
    {
        return self::tryFrom((string) $nilai) ?? self::Rencana;
    }
}
