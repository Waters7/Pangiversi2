<?php

namespace App\Services;

use App\Models\SpdPelaksana;
use App\Models\User;
use App\Models\Usulan;
use Carbon\Carbon;

/**
 * Penomoran dokumen perjalanan dinas.
 *
 * Nomor perjadin dibentuk PJ-KodeUnit-Tahun-Bulan-Urut, sedangkan nomor surat
 * tugas mengikuti klasifikasi arsip Kemenkes yang dipakai Poltekkes Kemenkes
 * Manado — keduanya masih dapat disunting bila penomoran resminya berbeda.
 */
class PenomoranPerjadin
{
    /** Kode satuan kerja pada penomoran surat Kemenkes. */
    public const KODE_SATKER = 'F.XXX';

    /** Dipakai bila unit kerja pegawai belum tercatat. */
    private const UNIT_BAWAAN = 'UMUM';

    /**
     * Nomor perjadin: PJ-KEP-2026-08-001.
     *
     * Urutannya dihitung per unit kerja pada bulan yang sama, sehingga tiap
     * jurusan punya deret nomornya sendiri.
     */
    public function nomorPerjadin(?User $pemilik, ?string $tanggalMulai = null): string
    {
        $awalan = $this->awalan($pemilik, $tanggalMulai);

        return $awalan.str_pad((string) $this->urutanBerikutnya($awalan), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor Surat Perjalanan Dinas, berpola sama dengan nomor perjadin.
     *
     * Satu SPD berlaku untuk satu usulan perjalanan dinas, sehingga keduanya
     * memakai pola yang sama agar mudah dipasangkan saat penelusuran berkas.
     * Deretnya dihitung sendiri dari nomor SPD yang sudah terbit, bukan dari
     * nomor usulan — SPD terbit lebih dulu, dan usulannya belum tentu ada.
     *
     * Nomor resmi yang tercetak pada dokumen tidak lagi berasal dari sini:
     * SRIKANDI yang memberikannya lewat penanda ${nomor_naskah}. Nomor ini
     * dipakai sebagai penanda internal dan rujukan pencarian.
     */
    public function nomorSpd(?User $pemilik, ?string $tanggalMulai = null): string
    {
        $awalan = $this->awalan($pemilik, $tanggalMulai);

        $terakhir = SpdPelaksana::where('nomor_surat', 'like', $awalan.'%')
            ->orderByDesc('nomor_surat')
            ->value('nomor_surat');

        $urut = $terakhir ? ((int) mb_substr($terakhir, mb_strlen($awalan))) + 1 : 1;

        return $awalan.str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Awalan bersama kedua penomoran: PJ-KodeUnit-Tahun-Bulan-.
     */
    private function awalan(?User $pemilik, ?string $tanggalMulai = null): string
    {
        $bulan = $tanggalMulai ? Carbon::parse($tanggalMulai) : now();

        return sprintf(
            'PJ-%s-%s-%s-',
            $this->kodeUnit($pemilik),
            now()->format('Y'),
            $bulan->format('m'),
        );
    }

    /**
     * Kode unit kerja pemilik usulan, misalnya KEP untuk Jurusan Keperawatan.
     */
    public function kodeUnit(?User $pemilik): string
    {
        $kode = $pemilik?->unit?->kode;

        return $kode ? mb_strtoupper($kode) : self::UNIT_BAWAAN;
    }

    /**
     * Urutan berikutnya untuk sebuah awalan nomor.
     *
     * Dihitung dari nomor tertinggi yang sudah terpakai, bukan dari jumlah
     * baris, agar penghapusan usulan tidak membuat nomornya terpakai ulang.
     */
    private function urutanBerikutnya(string $awalan): int
    {
        $terakhir = Usulan::where('no_usulan', 'like', $awalan.'%')
            ->orderByDesc('no_usulan')
            ->value('no_usulan');

        if (! $terakhir) {
            return 1;
        }

        return ((int) mb_substr($terakhir, mb_strlen($awalan))) + 1;
    }
}
