<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use App\Models\Usulan;

/**
 * Menerbitkan daftar nominatif begitu pelaksana pertama di bawah surat
 * tugasnya tuntas, dan memberi tahu PPK bahwa ada daftar yang menunggu
 * tanda tangannya — dulu daftar baru terbit saat PPK membuka antreannya.
 */
class PenerbitNominatif
{
    public function __construct(
        private PenyusunNominatif $penyusun,
        private NotifikasiService $notifikasi,
    ) {}

    public function terbitkan(Usulan $usulan): void
    {
        $daftar = $this->penyusun->terbitkanBilaSiap($usulan);

        if (! $daftar) {
            return;
        }

        $this->notifikasi->kirimKePeran(
            [User::ROLE_PPK],
            'Daftar nominatif menunggu tanda tangan',
            "Daftar nominatif surat tugas {$daftar->no_tugas} terbit dan menunggu tanda tangan Anda.",
            [
                'tipe' => Notifikasi::TIPE_INFO,
                'url' => route('persetujuan.nominatif'),
            ],
        );
    }
}
