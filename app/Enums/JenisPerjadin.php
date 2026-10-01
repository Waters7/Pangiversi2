<?php

namespace App\Enums;

use App\Models\KategoriPerjadin;
use Illuminate\Support\Collection;

/**
 * Tiga jalur pengajuan perjalanan dinas, ditanyakan sebelum formulir dibuka.
 *
 * Pilihan ini memilah kategori perjadin dari master data dan menentukan
 * berkas apa yang diminta: perjalanan biasa berdasar SPD bertanda tangan,
 * sedangkan supervisi kerja praktek cukup surat tugas.
 */
enum JenisPerjadin: string
{
    case DalamKota = 'dalam-kota';

    case LuarKota = 'luar-kota';

    case Supervisi = 'supervisi';

    /** Grup master data untuk supervisi kerja praktek dan magang. */
    public const GRUP_SUPERVISI = 'Supervisi Kerja Praktek / Magang';

    /** Jenis kegiatan yang terisi sendiri pada jalur supervisi. */
    public const KEGIATAN_SUPERVISI = 'Supervisi kerja praktek / magang';

    public function label(): string
    {
        return match ($this) {
            self::DalamKota => 'Perjalanan Dalam Kota',
            self::LuarKota => 'Perjalanan Luar Kota',
            self::Supervisi => 'Supervisi Kerja Praktek / Magang',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::DalamKota => 'Tujuan di dalam kota: fullboard, fullday, halfday, atau sekadar transport lokal.',
            self::LuarKota => 'Tujuan ke luar kota atau luar negeri, dengan tiket dan penginapan.',
            self::Supervisi => 'Mendampingi mahasiswa di lokasi praktek atau magang.',
        };
    }

    /** Hal yang perlu disiapkan, ditampilkan pada kartu pilihan. */
    public function berkasDasar(): string
    {
        return $this->butuhSpd()
            ? 'Perlu SPD bertanda tangan beserta nomornya.'
            : 'Cukup surat tugas — tanpa SPD bertanda tangan.';
    }

    /**
     * Supervisi tidak menerbitkan SPD: dasar penugasannya surat tugas
     * jurusan, dan pertanggungjawabannya mengikuti surat itu.
     */
    public function butuhSpd(): bool
    {
        return $this !== self::Supervisi;
    }

    /** Jenis kegiatan yang dipaksakan oleh jalur ini, bila ada. */
    public function namaKegiatan(): ?string
    {
        return $this === self::Supervisi ? self::KEGIATAN_SUPERVISI : null;
    }

    /**
     * Kategori perjadin yang boleh dipilih pada jalur ini, dikelompokkan
     * per grup master data seperti pada formulir.
     *
     * @return Collection<string, Collection<int, KategoriPerjadin>>
     */
    public function kategori(): Collection
    {
        $kategori = KategoriPerjadin::aktif()->orderBy('urutan')->get();

        $terpilih = match ($this) {
            self::Supervisi => $kategori->where('grup', self::GRUP_SUPERVISI),
            self::DalamKota => $kategori->where('grup', '!=', self::GRUP_SUPERVISI)->where('dalam_kota', true),
            self::LuarKota => $kategori->where('grup', '!=', self::GRUP_SUPERVISI)->where('dalam_kota', false),
        };

        return $terpilih
            ->groupBy('grup')
            ->sortBy(function ($_, string $grup) {
                $posisi = array_search($grup, KategoriPerjadin::URUTAN_GRUP, true);

                return $posisi === false ? PHP_INT_MAX : $posisi;
            });
    }

    /** Id kategori yang sah untuk jalur ini. */
    public function idKategori(): array
    {
        return $this->kategori()->flatten()->pluck('id')->all();
    }

    public static function dari(?string $nilai): ?self
    {
        return self::tryFrom((string) $nilai);
    }

    /**
     * Jalur yang cocok untuk sebuah kategori — dipakai saat menyunting
     * usulan lama yang belum menyimpan pilihannya.
     */
    public static function untukKategori(?KategoriPerjadin $kategori): self
    {
        if (! $kategori) {
            return self::LuarKota;
        }

        if ($kategori->grup === self::GRUP_SUPERVISI) {
            return self::Supervisi;
        }

        return $kategori->dalamKota() ? self::DalamKota : self::LuarKota;
    }
}
