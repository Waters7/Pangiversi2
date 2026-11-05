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

    /**
     * Transport lokal perjalanan luar kota lintas pulau. Bukan baris rincian
     * biaya: ia dipertanggungjawabkan lewat Daftar Pengeluaran Riil.
     */
    case TransportLokal = 'transport_lokal';

    /**
     * Transport lokal jalur lain, yang tidak memakai Daftar Pengeluaran Riil:
     * baris rincian biaya biasa — ikut terhitung, tercetak, dan disahkan
     * bersama rincian biayanya.
     */
    case TransportLokalRincian = 'transport_lokal_rincian';

    case Penginapan = 'penginapan';

    /** Kontribusi/registrasi kegiatan yang dibayar pelaksana, berbukti invoice. */
    case Penyelenggaraan = 'penyelenggaraan';

    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Transport => 'Transport',
            self::UangHarian => 'Uang Harian',
            self::TransportLokal, self::TransportLokalRincian => 'Transport Lokal',
            self::Penginapan => 'Uang Penginapan',
            self::Penyelenggaraan => 'Biaya Penyelenggaraan',
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
            self::TransportLokal, self::TransportLokalRincian => 3,
            self::Penginapan => 4,
            self::Penyelenggaraan => 5,
            self::Lainnya => 6,
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
            self::TransportLokal, self::TransportLokalRincian => 'bg-indigo-100 text-indigo-700',
            self::Penginapan => 'bg-amber-100 text-amber-700',
            self::Penyelenggaraan => 'bg-rose-100 text-rose-700',
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
     * Pilihan kategori pada formulir tim keuangan.
     *
     * Pada perjalanan lintas pulau transport lokal tidak menjadi baris rincian
     * biaya: komponennya dicatat pada Daftar Pengeluaran Riil pelaksana,
     * bersama nota transportasinya, dan labelnya menyebut tujuan itu supaya
     * tidak disangka hilang. Jalur lain mencatatnya sebagai baris rincian.
     *
     * @return array<string, string>
     */
    public static function untukTimKeuangan(bool $memakaiDaftarRiil = true): array
    {
        $pilihan = [];

        foreach (self::options() as $nilai => $label) {
            match (true) {
                $nilai !== self::TransportLokal->value => $pilihan[$nilai] = $label,
                $memakaiDaftarRiil => $pilihan[$nilai] = $label.' → Daftar Riil',
                default => $pilihan[self::TransportLokalRincian->value] = $label,
            };
        }

        return $pilihan;
    }

    /**
     * Kategori transport lokal yang berlaku bagi jalur perjalanan, supaya
     * kiriman formulir lama tidak salah masuk dokumen.
     */
    public function untukJalur(bool $memakaiDaftarRiil): self
    {
        return match ($this) {
            self::TransportLokal, self::TransportLokalRincian => $memakaiDaftarRiil ? self::TransportLokal : self::TransportLokalRincian,
            default => $this,
        };
    }

    /**
     * Kelompok pada dokumen rincian. Kedua transport lokal satu kelompok.
     *
     * @return list<self>
     */
    public static function urutanCetak(): array
    {
        $kategori = array_filter(self::cases(), fn (self $item) => $item !== self::TransportLokalRincian);

        usort($kategori, fn (self $a, self $b) => $a->urutan() <=> $b->urutan());

        return $kategori;
    }

    public static function dari(?string $nilai): self
    {
        return self::tryFrom((string) $nilai) ?? self::Lainnya;
    }
}
