<?php

namespace App\Enums;

/**
 * Berkas pertanggungjawaban yang dapat ditagih sesudah perjalanan dinas.
 *
 * Mana saja yang wajib ditentukan per jalur pengajuan lewat Administrasi
 * Sistem → Berkas Pertanggungjawaban, bukan dipatok di dalam kode: satuan
 * kerja yang tidak memakai tiket, misalnya, tidak perlu ditagih tiket.
 */
enum BerkasLpj: string
{
    case Sppd = 'sppd';

    case Tiket = 'tiket';

    case NotaTransport = 'nota_transport';

    case BillHotel = 'bill_hotel';

    case Kuitansi = 'kuitansi';

    case Penyelenggaraan = 'penyelenggaraan';

    case Laporan = 'laporan';

    public function label(): string
    {
        return match ($this) {
            self::Sppd => 'SPPD bertanda tangan',
            self::Tiket => 'Tiket pergi dan pulang',
            self::NotaTransport => 'Nota transportasi lokal',
            self::BillHotel => 'Bill hotel / kuitansi hotel',
            self::Kuitansi => 'Kuitansi penyelenggara / hotel',
            self::Penyelenggaraan => 'Bukti biaya penyelenggaraan',
            self::Laporan => 'Laporan perjalanan dinas',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Sppd => 'Pindaian SPPD yang sudah ditandatangani pejabat di tempat tujuan; dilewati pada perjalanan yang tidak menerbitkan SPD.',
            self::Tiket => 'Boarding pass dan invoice untuk tiap arah, beserta nomor tiket dan kode bookingnya.',
            self::NotaTransport => 'Bukti tiap ruas transport lokal yang bernominal; ruas tanpa biaya tidak ditagih.',
            self::BillHotel => 'Satu berkas — bill hotel atau kuitansi dari hotel — beserta nomor transaksi dan nominalnya.',
            self::Kuitansi => 'Digabung dengan bill hotel: cukup satu berkas pada bagian akomodasi.',
            self::Penyelenggaraan => 'Bukti bayar kontribusi kegiatan — hanya ditagih bila pelaksana menyatakan ada.',
            self::Laporan => 'Diisi langsung di aplikasi lalu dikonfirmasi Direktur, bukan diunggah.',
        };
    }

    /**
     * Berkas yang dapat dipilih pada pengaturan per jalur. Kuitansi tidak
     * lagi berdiri sendiri — ia digabung dengan bill hotel menjadi satu
     * berkas — jadi tidak ditawarkan; pengaturan lama yang memuatnya tetap
     * terbaca sebagai bagian akomodasi.
     *
     * @return list<self>
     */
    public static function pilihan(): array
    {
        return array_values(array_filter(self::cases(), fn (self $berkas) => $berkas !== self::Kuitansi));
    }

    /** Berkas yang selalu ditagih bersyarat, bukan karena wajib. */
    public function bersyarat(): bool
    {
        return in_array($this, [self::NotaTransport, self::Penyelenggaraan], true);
    }

    /**
     * Susunan bawaan tiap jalur, dipakai selama administrator belum
     * mengaturnya sendiri.
     *
     * Nota transport hanya ditagih pada perjalanan luar kota lintas pulau.
     * Jalur lain tidak memiliki transport lokal, jadi daftar pengeluaran
     * riilnya kosong — tidak perlu ditandatangani — dan berkasnya langsung
     * dapat masuk daftar nominatif.
     *
     * @return list<self>
     */
    public static function bawaanUntuk(JenisPerjadin $jenis): array
    {
        return match ($jenis) {
            JenisPerjadin::LuarKota => self::cases(),
            // Dalam satu provinsi umumnya lewat jalan darat, jadi tiket tidak
            // ditagih; administrator dapat menambahkannya bila perlu.
            JenisPerjadin::LuarKotaDalamProvinsi => array_values(array_filter(
                self::cases(),
                fn (self $berkas) => ! in_array($berkas, [self::Tiket, self::NotaTransport], true),
            )),
            // Perjalanan dalam kota tidak memakai SPD, jadi SPPD pun tidak ditagih.
            JenisPerjadin::DalamKota => [self::Penyelenggaraan, self::Laporan],
            // Supervisi di dalam kota berdasar surat tugas saja — SPPD-nya
            // gugur sendiri lewat untukUsulan(), jadi yang tersisa di sini
            // berlaku untuk supervisi ke luar kota.
            JenisPerjadin::Supervisi => [self::Sppd, self::Penyelenggaraan, self::Laporan],
        };
    }

    public static function dari(?string $nilai): ?self
    {
        return self::tryFrom((string) $nilai);
    }
}
