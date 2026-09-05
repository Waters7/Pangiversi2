<?php

namespace App\Enums;

/**
 * Status proses usulan perjalanan dinas (FR-13).
 *
 * Status tahap keuangan (uang muka, dalam perjalanan, menunggu LPJ) tidak
 * diduplikasi di sini karena sudah direpresentasikan oleh status pada
 * modul keuangan dan kelengkapan dokumen pertanggungjawaban.
 */
enum StatusUsulan: string
{
    case Draft = 'draft';

    case MenungguPpk = 'menunggu_ppk';

    case PerluRevisi = 'perlu_revisi';

    case Disetujui = 'disetujui';

    case Selesai = 'selesai';

    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::MenungguPpk => 'Menunggu Validasi PPK',
            self::PerluRevisi => 'Perlu Revisi',
            // Usulan berlaku sejak diajukan — penugasannya sudah disahkan
            // lewat SPD. Yang berjalan pada tahap ini konfirmasi kesediaan
            // pelaksana, bukan persetujuan atasan.
            self::Disetujui => 'Konfirmasi',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800',
            self::MenungguPpk => 'bg-yellow-100 text-yellow-800',
            self::PerluRevisi => 'bg-amber-100 text-amber-800',
            self::Disetujui => 'bg-green-100 text-green-800',
            self::Selesai => 'bg-purple-100 text-purple-800',
            self::Ditolak => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Tahap validasi yang sedang menunggu keputusan, bila ada.
     */
    public function level(): ?LevelPersetujuan
    {
        return match ($this) {
            self::MenungguPpk => LevelPersetujuan::Ppk,
            default => null,
        };
    }

    /**
     * Usulan sedang berada dalam antrian validasi.
     */
    public function sedangMenunggu(): bool
    {
        return $this->level() !== null;
    }

    /**
     * Usulan masih boleh disunting oleh pengusul.
     */
    public function bolehDisunting(): bool
    {
        return in_array($this, [self::Draft, self::Ditolak, self::PerluRevisi], true);
    }

    /**
     * Usulan sudah tidak bergerak lagi dalam alur validasi.
     */
    public function final(): bool
    {
        return in_array($this, [self::Selesai, self::Ditolak], true);
    }

    /**
     * Perjalanan sudah dipastikan berlangsung.
     */
    public function sudahFix(): bool
    {
        return in_array($this, [self::Disetujui, self::Selesai], true);
    }

    /**
     * Semua status yang berarti usulan sedang menunggu keputusan.
     *
     * @return list<string>
     */
    public static function nilaiMenunggu(): array
    {
        return array_map(
            fn (LevelPersetujuan $level) => $level->statusMenunggu()->value,
            LevelPersetujuan::urutan(),
        );
    }

    /**
     * Daftar status untuk dropdown filter.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * Terjemahkan nilai status apa pun menjadi enum, dengan fallback aman
     * untuk data lama yang belum dimigrasikan.
     */
    public static function dari(?string $nilai): self
    {
        return match ($nilai) {
            'diajukan', 'menunggu', 'menunggu_atasan', 'menunggu_sdm', 'menunggu_direktur', 'menunggu_pimpinan' => self::MenungguPpk,
            'revisi_sdm' => self::PerluRevisi,
            default => self::tryFrom((string) $nilai) ?? self::Draft,
        };
    }
}
