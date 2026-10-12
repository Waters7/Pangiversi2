<?php

namespace App\Enums;

/**
 * Pesan WhatsApp yang disusun aplikasi, formatnya dapat diubah dari
 * Administrasi Sistem → Format WhatsApp.
 *
 * Tiap format memakai penanda berkurung kurawal — misalnya {nama} — yang
 * diganti nilainya saat pesan disusun. Format bawaan sama persis dengan
 * pesan sebelum menu ini ada.
 */
enum PesanWhatsapp: string
{
    case PenagihanBerkas = 'penagihan_berkas';

    case GangguanServer = 'gangguan_server';

    case PengingatServer = 'pengingat_server';

    case PulihServer = 'pulih_server';

    case UjiServer = 'uji_server';

    public function label(): string
    {
        return match ($this) {
            self::PenagihanBerkas => 'Penagihan berkas pertanggungjawaban',
            self::GangguanServer => 'Peringatan server tidak dapat diakses',
            self::PengingatServer => 'Pengingat server masih belum pulih',
            self::PulihServer => 'Kabar server kembali normal',
            self::UjiServer => 'Uji peringatan server',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::PenagihanBerkas => 'Dikirim tim keuangan kepada pelaksana lewat tombol Tagih berkas pada menu Keuangan.',
            self::GangguanServer => 'Dikirim pemantau server ke nomor peringatan saat aplikasi pertama kali tidak dapat diakses.',
            self::PengingatServer => 'Diulang ke nomor peringatan selama gangguan belum pulih.',
            self::PulihServer => 'Dikirim ke nomor peringatan saat aplikasi kembali dapat diakses.',
            self::UjiServer => 'Dikirim lewat tombol Kirim Uji pada Pemantauan Server.',
        };
    }

    /** Pesan pemantau server — tetap terkirim meski basis data mati. */
    public function pesanServer(): bool
    {
        return $this !== self::PenagihanBerkas;
    }

    /**
     * Penanda yang dapat dipakai beserta artinya.
     *
     * @return array<string, string>
     */
    public function penanda(): array
    {
        return match ($this) {
            self::PenagihanBerkas => [
                'nama' => 'Nama pelaksana',
                'no_usulan' => 'Nomor usulan perjadin',
                'lokasi' => 'Tujuan perjalanan',
                'tanggal_selesai' => 'Tanggal perjalanan berakhir',
                'daftar_berkas' => 'Daftar berkas yang belum diunggah, bernomor',
                'jumlah_berkas' => 'Banyaknya berkas yang belum diunggah',
            ],
            self::GangguanServer => [
                'sejak' => 'Waktu gangguan mulai terdeteksi',
                'alamat' => 'Alamat aplikasi',
                'hasil_pemeriksaan' => 'Hasil pemeriksaan situs, basis data, dan penyimpanan',
            ],
            self::PengingatServer => [
                'lama' => 'Lamanya gangguan sejauh ini',
                'sejak' => 'Waktu gangguan mulai terdeteksi',
                'alamat' => 'Alamat aplikasi',
                'hasil_pemeriksaan' => 'Hasil pemeriksaan situs, basis data, dan penyimpanan',
            ],
            self::PulihServer => [
                'pulih' => 'Waktu aplikasi kembali normal',
                'lama' => 'Lamanya gangguan',
                'alamat' => 'Alamat aplikasi',
            ],
            self::UjiServer => [
                'alamat' => 'Alamat aplikasi',
                'waktu' => 'Waktu pesan uji dikirim',
            ],
        };
    }

    /**
     * Penanda yang wajib ada — tanpanya pesan kehilangan isi pokoknya.
     *
     * @return list<string>
     */
    public function penandaWajib(): array
    {
        return match ($this) {
            self::PenagihanBerkas => ['daftar_berkas'],
            self::GangguanServer, self::PengingatServer => ['hasil_pemeriksaan'],
            default => [],
        };
    }

    /**
     * Nilai contoh untuk pratinjau.
     *
     * @return array<string, string>
     */
    public function contoh(): array
    {
        return match ($this) {
            self::PenagihanBerkas => [
                'nama' => 'Maya Sari Wulandari, S.Tr.Keb',
                'no_usulan' => 'PJ-DIR-2026-10-001',
                'lokasi' => 'Jakarta',
                'tanggal_selesai' => '14 Oktober 2026',
                'daftar_berkas' => "1. Tiket Pulang (boarding pass)\n2. Bill hotel",
                'jumlah_berkas' => '2',
            ],
            self::GangguanServer, self::PengingatServer => [
                'lama' => '1 jam 5 menit',
                'sejak' => '12 Okt 2026, 09.15 WITA',
                'alamat' => 'https://pangi.poltekkes-manado.ac.id',
                'hasil_pemeriksaan' => "- Situs: tidak menjawab dalam 10 detik\n- Basis data: tersambung\n- Penyimpanan: dapat ditulis",
            ],
            self::PulihServer => [
                'pulih' => '12 Okt 2026, 10.20 WITA',
                'lama' => '1 jam 5 menit',
                'alamat' => 'https://pangi.poltekkes-manado.ac.id',
            ],
            self::UjiServer => [
                'alamat' => 'https://pangi.poltekkes-manado.ac.id',
                'waktu' => '12 Okt 2026, 09.00 WITA',
            ],
        };
    }

    /** Format bawaan — sama dengan pesan sebelum formatnya dapat diubah. */
    public function bawaan(): string
    {
        return match ($this) {
            self::PenagihanBerkas => "Selamat pagi/siang {nama},\n\n"
                .'Menindaklanjuti perjalanan dinas {no_usulan} ke {lokasi} yang berakhir {tanggal_selesai}, '
                ."berkas pertanggungjawaban berikut belum kami terima:\n\n"
                ."{daftar_berkas}\n\n"
                ."Mohon diunggah melalui aplikasi PANGI pada menu Dokumen agar pembayaran dapat kami proses.\n\n"
                .'Terima kasih. — Tim Keuangan Poltekkes Kemenkes Manado',
            self::GangguanServer => "*PERINGATAN — PANGI tidak dapat diakses*\n"
                ."Sejak: {sejak}\n"
                ."Alamat: {alamat}\n\n"
                ."Hasil pemeriksaan:\n"
                ."{hasil_pemeriksaan}\n\n"
                .'Pesan otomatis dari pemantau server PANGI.',
            self::PengingatServer => "*PANGI masih belum dapat diakses* — sudah {lama}\n"
                ."Sejak: {sejak}\n"
                ."Alamat: {alamat}\n\n"
                ."Hasil pemeriksaan:\n"
                ."{hasil_pemeriksaan}\n\n"
                .'Pesan otomatis dari pemantau server PANGI.',
            self::PulihServer => "*PANGI kembali normal*\n"
                ."Pulih: {pulih}, setelah gangguan sekitar {lama}.\n"
                ."Alamat: {alamat}\n\n"
                .'Pesan otomatis dari pemantau server PANGI.',
            self::UjiServer => "*Uji peringatan PANGI*\n"
                ."Pesan ini menandakan peringatan WhatsApp pemantau server sudah tersambung.\n\n"
                ."Alamat: {alamat}\n"
                .'Waktu: {waktu}',
        };
    }

    /** Kunci penyimpanannya pada tabel pengaturan. */
    public function kunciPengaturan(): string
    {
        return 'format_wa_'.$this->value;
    }
}
