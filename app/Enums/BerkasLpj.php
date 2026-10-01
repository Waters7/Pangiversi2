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
            self::BillHotel => 'Bill hotel',
            self::Kuitansi => 'Kuitansi penyelenggara / hotel',
            self::Penyelenggaraan => 'Bukti biaya penyelenggaraan',
            self::Laporan => 'Laporan perjalanan dinas',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Sppd => 'Pindaian SPPD yang sudah ditandatangani pejabat di tempat tujuan.',
            self::Tiket => 'Boarding pass dan invoice untuk tiap arah, beserta nomor tiket dan kode bookingnya.',
            self::NotaTransport => 'Bukti tiap ruas transport lokal yang bernominal; ruas tanpa biaya tidak ditagih.',
            self::BillHotel => 'Bill hotel beserta nomor transaksi dan nominalnya.',
            self::Kuitansi => 'Kuitansi resmi dari penyelenggara kegiatan atau dari hotel tempat menginap.',
            self::Penyelenggaraan => 'Bukti bayar kontribusi kegiatan — hanya ditagih bila pelaksana menyatakan ada.',
            self::Laporan => 'Diisi langsung di aplikasi lalu dikonfirmasi Direktur, bukan diunggah.',
        };
    }

    /** Berkas yang selalu ditagih bersyarat, bukan karena wajib. */
    public function bersyarat(): bool
    {
        return in_array($this, [self::NotaTransport, self::Penyelenggaraan], true);
    }

    /**
     * Susunan bawaan tiap jalur — sama persis dengan aturan sebelum
     * pengaturan ini ada, sehingga pemasangan lama tidak berubah perilakunya.
     *
     * @return list<self>
     */
    public static function bawaanUntuk(JenisPerjadin $jenis): array
    {
        return match ($jenis) {
            JenisPerjadin::LuarKota => self::cases(),
            JenisPerjadin::DalamKota => [self::Sppd, self::NotaTransport, self::Penyelenggaraan, self::Laporan],
            // Supervisi berdasar surat tugas, bukan SPPD; berkas lain
            // ditambahkan sendiri oleh administrator bila memang diperlukan.
            JenisPerjadin::Supervisi => [self::NotaTransport, self::Penyelenggaraan, self::Laporan],
        };
    }

    public static function dari(?string $nilai): ?self
    {
        return self::tryFrom((string) $nilai);
    }
}
