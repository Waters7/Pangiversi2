<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\ImporPengguna;
use App\Services\SumberPegawaiBerkas;
use Illuminate\Database\Seeder;

/**
 * Memuat pegawai Poltekkes Kemenkes Manado dari berkas
 * database/data/pegawai-poltekkes.csv, yang disarikan dari Laporan Daftar
 * Urut Kepangkatan (DUK) Agustus 2026.
 *
 * Berkasnya sengaja tidak memuat NIK: aplikasi tidak membutuhkannya.
 * Kata sandi awal tiap akun adalah NIP-nya sendiri dan wajib diganti
 * pengguna lewat menu Profil.
 *
 * Aman dijalankan ulang di produksi untuk menambah pegawai: akun yang sudah
 * ada hanya dilengkapi pada kolom yang masih kosong, dan garis atasan hanya
 * diisi bagi yang belum punya — isian pengguna maupun susunan yang sudah
 * diubah administrator tidak ditimpa.
 */
class PegawaiPoltekkesSeeder extends Seeder
{
    public function run(): void
    {
        $berkas = database_path('data/pegawai-poltekkes.csv');

        if (! is_readable($berkas)) {
            $this->command?->warn("Berkas pegawai tidak ditemukan: {$berkas}");

            return;
        }

        $hasil = app(ImporPengguna::class)->jalankan(new SumberPegawaiBerkas($berkas), hanyaMelengkapi: true);

        $this->tetapkanAtasan();

        $this->command?->info(
            "Pegawai dimuat: {$hasil['dibuat']} baru, {$hasil['diperbarui']} diperbarui."
        );

        foreach ($hasil['dilewati'] as $catatan) {
            $this->command?->warn($catatan);
        }
    }

    /**
     * Susun garis atasan bagi yang belum punya: pegawai jurusan mengarah ke
     * ketua jurusannya, sedangkan pegawai direktorat mengarah ke Direktur.
     */
    private function tetapkanAtasan(): void
    {
        $direktur = User::where('role', PeranPengguna::Pimpinan->value)
            ->where('jabatan', 'Direktur')
            ->first();

        if (! $direktur) {
            return;
        }

        foreach (UnitKerja::all() as $unit) {
            $ketua = User::where('id_unit', $unit->id)
                ->where('jabatan', 'like', 'Ketua Jurusan%')
                ->first();

            $atasan = $ketua ?? $direktur;

            User::where('id_unit', $unit->id)
                ->whereKeyNot($atasan->id)
                ->whereNull('id_atasan')
                ->update(['id_atasan' => $atasan->id]);

            if ($ketua && $ketua->id !== $direktur->id && $ketua->id_atasan === null) {
                $ketua->update(['id_atasan' => $direktur->id]);
            }
        }

        $direktur->update(['id_atasan' => null]);
    }
}
