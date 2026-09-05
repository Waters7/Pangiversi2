<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Kontrak sumber data pegawai.
 *
 * Saat ini PANGI mengambil data pegawai dari berkas CSV yang diunggah Tim SDM.
 * Bila nanti tersedia aplikasi kepegawaian, cukup dibuat implementasi baru
 * (misalnya pemanggil API) tanpa mengubah controller maupun importer.
 */
interface SumberDataPegawai
{
    /**
     * Ambil data pegawai sebagai baris-baris siap impor.
     *
     * Setiap baris memakai kunci yang sama dengan header CSV:
     * nama, nip, email, role, jabatan, unit_kode, atasan_nip,
     * nama_bank, nomor_rekening, nama_rekening.
     *
     * @return Collection<int, array<string, string|null>>
     */
    public function ambil(): Collection;

    /**
     * Nama sumber untuk ditampilkan pada ringkasan impor dan jejak audit.
     */
    public function nama(): string;
}
