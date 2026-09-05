<?php

namespace App\Services;

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
        $kodeUnit = $this->kodeUnit($pemilik);
        $bulanUsulan = $tanggalMulai ? Carbon::parse($tanggalMulai) : now();

        $awalan = sprintf(
            'PJ-%s-%s-%s-',
            $kodeUnit,
            now()->format('Y'),
            $bulanUsulan->format('m'),
        );

        return $awalan.str_pad((string) $this->urutanBerikutnya($awalan), 3, '0', STR_PAD_LEFT);
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
