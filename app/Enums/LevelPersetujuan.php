<?php

namespace App\Enums;

/**
 * Tahapan validasi usulan perjalanan dinas.
 *
 * Cukup PPK yang memvalidasi: surat tugas yang diajukan sudah sepengetahuan
 * atasan langsung dan Kasubbag Umum sebelum masuk ke sistem, sehingga tahap
 * atasan dan pimpinan tidak lagi diulang di dalam aplikasi.
 */
enum LevelPersetujuan: int
{
    case Ppk = 1;

    public function label(): string
    {
        return match ($this) {
            self::Ppk => 'PPK',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Ppk => 'Validasi kegiatan, kelengkapan berkas, dan kesesuaian anggaran',
        };
    }

    /**
     * Peran pengguna yang berwenang memutuskan pada tahap ini.
     */
    public function peran(): ?PeranPengguna
    {
        return match ($this) {
            self::Ppk => PeranPengguna::Ppk,
        };
    }

    /**
     * Status usulan selama menunggu keputusan pada tahap ini.
     */
    public function statusMenunggu(): StatusUsulan
    {
        return match ($this) {
            self::Ppk => StatusUsulan::MenungguPpk,
        };
    }

    /**
     * Tahap berikutnya, atau null bila ini tahap terakhir.
     */
    public function berikutnya(): ?self
    {
        return self::tryFrom($this->value + 1);
    }

    public static function pertama(): self
    {
        return self::Ppk;
    }

    /**
     * @return list<self>
     */
    public static function urutan(): array
    {
        return self::cases();
    }
}
