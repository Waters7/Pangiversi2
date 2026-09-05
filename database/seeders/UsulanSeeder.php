<?php

namespace Database\Seeders;

use App\Enums\StatusUsulan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Database\Seeder;

class UsulanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sebaran status dibuat pasti agar seeder turunan (dokumen dan keuangan)
        // selalu punya usulan yang sudah disetujui untuk dikaitkan.
        $sebaran = [
            StatusUsulan::Draft->value => 2,
            StatusUsulan::MenungguPpk->value => 4,
            StatusUsulan::PerluRevisi->value => 1,
            StatusUsulan::Disetujui->value => 4,
            StatusUsulan::Ditolak->value => 1,
            StatusUsulan::Selesai->value => 2,
        ];

        foreach ($sebaran as $status => $jumlah) {
            Usulan::factory()->count($jumlah)->create(['status' => $status]);
        }

        $this->rombonganMenungguKonfirmasi();
    }

    /**
     * Satu rombongan yang dibuatkan seorang pengusul untuk dua rekannya,
     * supaya aksi konfirmasi dan pembatalan pada daftar usulan ada contohnya.
     */
    private function rombonganMenungguKonfirmasi(): void
    {
        $pengusul = User::where('role', User::ROLE_DOSEN_TENDIK)->first();
        $rekan = User::where('role', User::ROLE_DOSEN_TENDIK)
            ->whereKeyNot($pengusul?->id)
            ->take(2)
            ->get();

        if (! $pengusul || $rekan->count() < 2) {
            return;
        }

        $kode = 'RBG-'.now()->format('Ymd').'-DEMO';

        // Pengusul sendiri langsung masuk antrian PPK.
        Usulan::factory()->create([
            'id_user' => $pengusul->id,
            'id_pembuat' => $pengusul->id,
            'kode_rombongan' => $kode,
            'jenis_pengajuan' => Usulan::PENGAJUAN_KELOMPOK,
            'konfirmasi' => Usulan::KONFIRMASI_DIKONFIRMASI,
            'dikonfirmasi_at' => now(),
            'status' => StatusUsulan::MenungguPpk->value,
        ]);

        // Rekan yang dibuatkan masih menunggu kesediaannya sendiri.
        foreach ($rekan as $anggota) {
            Usulan::factory()->create([
                'id_user' => $anggota->id,
                'id_pembuat' => $pengusul->id,
                'kode_rombongan' => $kode,
                'jenis_pengajuan' => Usulan::PENGAJUAN_KELOMPOK,
                'konfirmasi' => Usulan::KONFIRMASI_MENUNGGU,
                'dikonfirmasi_at' => null,
                'status' => StatusUsulan::Draft->value,
            ]);
        }
    }
}
