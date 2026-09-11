<?php

namespace App\Enums;

/**
 * Tahapan laporan perjalanan dinas, dari disusun sampai dikonfirmasi pimpinan.
 *
 * Statusnya diturunkan dari cap waktu pada laporan, bukan disimpan sebagai
 * kolom sendiri, sehingga tidak pernah berselisih dengan kejadian yang
 * tercatat.
 */
enum StatusLaporanPerjadin: string
{
    case Draf = 'draf';

    case Selesai = 'selesai';

    case MenungguKonfirmasi = 'menunggu_konfirmasi';

    case PerluRevisi = 'perlu_revisi';

    case Dikonfirmasi = 'dikonfirmasi';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Belum Selesai',
            self::Selesai => 'Selesai, Belum Dikirim',
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi Pimpinan',
            self::PerluRevisi => 'Perlu Revisi',
            self::Dikonfirmasi => 'Dikonfirmasi Pimpinan',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Draf => 'Pelaksana masih menyusun isinya.',
            self::Selesai => 'Sudah dinyatakan selesai, tinggal dikirim ke pimpinan.',
            self::MenungguKonfirmasi => 'Sudah dikirim pelaksana, menunggu ditandatangani pimpinan.',
            self::PerluRevisi => 'Dikembalikan pimpinan dengan catatan yang harus diperbaiki.',
            self::Dikonfirmasi => 'Sudah ditandatangani pimpinan dan terkunci.',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draf => 'bg-slate-100 text-slate-600',
            self::Selesai => 'bg-blue-100 text-blue-700',
            self::MenungguKonfirmasi => 'bg-amber-100 text-amber-700',
            self::PerluRevisi => 'bg-red-100 text-red-700',
            self::Dikonfirmasi => 'bg-emerald-100 text-emerald-700',
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
}
