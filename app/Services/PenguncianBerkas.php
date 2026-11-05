<?php

namespace App\Services;

use App\Models\DaftarRiil;
use App\Models\Usulan;

/**
 * Penjaga keutuhan berkas yang sudah ditandatangani.
 *
 * Tanda tangan menyatakan persetujuan atas angka tertentu. Begitu pelaksana
 * atau PPK membubuhkannya, angka itu tidak boleh lagi berubah diam-diam:
 * dokumen yang sudah tercetak, QR yang sudah dipindai, dan daftar nominatif
 * yang menumpang di atasnya semuanya menunjuk nilai yang sama.
 *
 * Kelas ini memusatkan aturan penguncian itu supaya seluruh titik ubah —
 * nominal pada formulir pelaksana, penyuntingan tim keuangan, penyelarasan
 * otomatis — memakai jawaban yang sama. Menjawab null berarti boleh diubah;
 * menjawab kalimat berarti terkunci, sekaligus menyebutkan jalan membukanya.
 *
 * Yang dikunci nominalnya, bukan berkasnya: boarding pass, nota, dan bukti
 * lain tetap dapat diunggah sesudah dokumen ditandatangani, sebab berkas
 * pertanggungjawaban kerap baru lengkap setelah rincian biayanya disahkan.
 */
class PenguncianBerkas
{
    /**
     * Alasan rincian biaya terkunci, atau null bila masih boleh diubah.
     */
    public function rincianBiaya(Usulan $usulan): ?string
    {
        return $this->alasan($this->berkas($usulan), JalurPersetujuan::RINCIAN, 'Rincian biaya perjalanan dinas');
    }

    /**
     * Alasan daftar pengeluaran riil terkunci, atau null.
     */
    public function daftarRiil(Usulan $usulan): ?string
    {
        return $this->alasan($this->berkas($usulan), JalurPersetujuan::RIIL, 'Daftar pengeluaran riil');
    }

    /**
     * Alasan penyelarasan nominal pelaksana ditahan, atau null.
     *
     * Tiket, nota, dan bill hotel adalah sumber kedua dokumen sekaligus,
     * jadi salah satu dokumen yang sudah ditandatangani sudah cukup untuk
     * menahannya — mengubah sumbernya akan menggeser angka pada dokumen
     * yang sudah disetujui.
     */
    public function unggahanPelaksana(Usulan $usulan): ?string
    {
        return $this->rincianBiaya($usulan) ?? $this->daftarRiil($usulan);
    }

    /**
     * Alasan nominal nota transport terkunci, atau null: nota mengalir ke
     * daftar riil pada perjalanan lintas pulau, dan ke rincian biaya pada
     * jalur lain, jadi dokumen itulah yang menguncinya.
     */
    public function notaTransport(Usulan $usulan): ?string
    {
        return $usulan->memakaiDaftarRiil() ? $this->daftarRiil($usulan) : $this->rincianBiaya($usulan);
    }

    /**
     * Berkas terkunci menghentikan tindakan dengan pesan yang menerangkan
     * apa yang mengunci dan bagaimana membukanya.
     */
    public function pastikanRincianTerbuka(Usulan $usulan): void
    {
        abort_if((bool) ($pesan = $this->rincianBiaya($usulan)), 403, $pesan ?? '');
    }

    public function pastikanRiilTerbuka(Usulan $usulan): void
    {
        abort_if((bool) ($pesan = $this->daftarRiil($usulan)), 403, $pesan ?? '');
    }

    private function berkas(Usulan $usulan): ?DaftarRiil
    {
        return DaftarRiil::firstWhere('id_usulan', $usulan->id);
    }

    /**
     * Tanda tangan PPK mengunci lebih rapat daripada persetujuan pelaksana,
     * jadi keduanya menyebutkan jalan keluar yang berbeda.
     */
    private function alasan(?DaftarRiil $berkas, string $jenis, string $nama): ?string
    {
        if (! $berkas) {
            return null;
        }

        $jalur = $berkas->jalur($jenis);

        if ($jalur->sudahDitandatangani()) {
            return "{$nama} sudah ditandatangani PPK dan tidak dapat diubah. "
                .'Mintalah PPK mencabut tanda tangannya lebih dulu.';
        }

        if ($jalur->sudahDisetujui()) {
            return "{$nama} sudah ditandatangani pelaksana dan tidak dapat diubah. "
                .'Bila nominalnya memang perlu diperbaiki, mintalah PPK mengembalikan berkasnya ke tim keuangan.';
        }

        return null;
    }
}
