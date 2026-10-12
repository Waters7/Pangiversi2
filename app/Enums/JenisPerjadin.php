<?php

namespace App\Enums;

use App\Models\KategoriPerjadin;
use Illuminate\Support\Collection;

/**
 * Jalur pengajuan perjalanan dinas, ditanyakan sebelum formulir dibuka.
 *
 * Pilihan ini memilah kategori perjadin dari master data dan menentukan
 * berkas apa yang diminta: perjalanan dalam kota cukup surat tugas tanpa
 * SPD, perjalanan luar kota — dalam satu provinsi maupun lintas pulau —
 * berdasar SPD bertanda tangan, sedangkan supervisi kerja praktek mengikuti
 * kategorinya.
 */
enum JenisPerjadin: string
{
    case DalamKota = 'dalam-kota';

    /**
     * Luar kota yang masih dalam satu provinsi — umumnya lewat jalan darat,
     * jadi tiket tidak ditagih secara bawaan.
     */
    case LuarKotaDalamProvinsi = 'luar-kota-provinsi';

    /** Nilainya tetap "luar-kota" supaya usulan dan pengaturan lama terbaca. */
    case LuarKota = 'luar-kota';

    case Supervisi = 'supervisi';

    /** Grup master data untuk supervisi kerja praktek dan magang. */
    public const GRUP_SUPERVISI = 'Supervisi Kerja Praktek / Magang';

    /** Jenis kegiatan yang terisi sendiri pada jalur supervisi. */
    public const KEGIATAN_SUPERVISI = 'Supervisi kerja praktek / magang';

    public function label(): string
    {
        return match ($this) {
            self::DalamKota => 'Perjalanan Dalam Kota Tidak Menggunakan SPD',
            self::LuarKotaDalamProvinsi => 'Perjalanan Luar Kota Dalam Satu Provinsi',
            self::LuarKota => 'Perjalanan Luar Kota Lintas Pulau',
            self::Supervisi => 'Supervisi Kerja Praktek / Magang',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::DalamKota => 'Tujuan di dalam kota: fullboard, fullday, halfday, atau sekadar transport lokal.',
            self::LuarKotaDalamProvinsi => 'Tujuan ke luar kota yang masih dalam satu provinsi, umumnya lewat jalan darat.',
            self::LuarKota => 'Tujuan ke luar kota lintas pulau atau luar negeri, dengan tiket dan penginapan.',
            self::Supervisi => 'Mendampingi mahasiswa di lokasi praktek atau magang, di dalam maupun luar kota.',
        };
    }

    /** Hal yang perlu disiapkan, ditampilkan pada kartu pilihan. */
    public function berkasDasar(): string
    {
        return match ($this) {
            self::DalamKota => 'Cukup surat tugas beserta nomornya — tidak memakai SPD.',
            self::Supervisi => 'Surat tugas jurusan — SPD hanya bila tujuannya luar kota.',
            default => 'Perlu SPD bertanda tangan beserta nomornya.',
        };
    }

    /**
     * Apakah perjalanan ini berdasar SPD bertanda tangan.
     *
     * Perjalanan dalam kota tidak memakai SPD — dasarnya surat tugas.
     * Perjalanan luar kota selalu menerbitkannya. Supervisi menentukannya
     * dari kategori yang dipilih: supervisi ke luar kota tetap memakai SPD,
     * sedangkan yang di dalam kota cukup surat tugas jurusan. Selama
     * kategorinya belum dipilih, supervisi dianggap belum memerlukannya.
     */
    public function butuhSpd(?KategoriPerjadin $kategori = null): bool
    {
        return match ($this) {
            self::DalamKota => false,
            self::Supervisi => $kategori !== null && ! $kategori->dalamKota(),
            default => true,
        };
    }

    /** Jalur yang kebutuhan SPD-nya ditentukan kategori, bukan jalurnya. */
    public function spdIkutKategori(): bool
    {
        return $this === self::Supervisi;
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
            // Kategori luar kota yang sama, kecuali luar negeri — yang jelas
            // tidak berada dalam satu provinsi.
            self::LuarKotaDalamProvinsi => $kategori->where('grup', '!=', self::GRUP_SUPERVISI)->where('dalam_kota', false)
                ->where('kode', '!=', KategoriPerjadin::KODE_LUAR_NEGERI),
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

    /**
     * Id kategori jalur ini yang tetap menerbitkan SPD — dipakai formulir
     * untuk memunculkan isian SPD hanya pada kategori yang memerlukannya.
     *
     * @return list<string>
     */
    public function idKategoriBerSpd(): array
    {
        return $this->kategori()
            ->flatten()
            ->filter(fn (KategoriPerjadin $kategori) => $this->butuhSpd($kategori))
            ->map(fn (KategoriPerjadin $kategori) => (string) $kategori->id)
            ->values()
            ->all();
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
