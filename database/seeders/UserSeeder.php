<?php

namespace Database\Seeders;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $direktorat = UnitKerja::firstWhere('kode', 'DIR');
        $adum = UnitKerja::firstWhere('kode', 'ADUM');
        $jurusan = UnitKerja::whereNotIn('kode', ['DIR', 'ADUM'])->get();

        // Pimpinan — approver puncak, tidak punya atasan.
        $pimpinan = User::factory()->create([
            'nama' => 'Dr. Direktur Poltekkes',
            'email' => 'direktur@poltekkes.ac.id',
            'nip' => 'NIP-0000000002',
            'role' => User::ROLE_PIMPINAN,
            'jabatan' => 'Direktur',
            'id_unit' => $direktorat?->id,
        ]);

        User::factory()->create([
            'nama' => 'Wakil Direktur II',
            'email' => 'wadir2@poltekkes.ac.id',
            'nip' => 'NIP-0000000009',
            'role' => User::ROLE_PIMPINAN,
            'jabatan' => 'Wakil Direktur Bidang Umum dan Keuangan',
            'id_unit' => $direktorat?->id,
            'id_atasan' => $pimpinan->id,
        ]);

        // Super administrator sistem.
        User::factory()->create([
            'nama' => 'Admin PANGI',
            'email' => 'admin@poltekkes.ac.id',
            'nip' => 'NIP-0000000001',
            'role' => User::ROLE_SUPER_ADMIN,
            'jabatan' => 'Super Administrator',
            'id_unit' => $adum?->id,
            'id_atasan' => $pimpinan->id,
        ]);

        // PPK — validasi perjadin dan tanda tangan daftar pengeluaran riil.
        $ppk = User::factory()->create([
            'nama' => 'Pejabat Pembuat Komitmen',
            'email' => 'ppk@poltekkes.ac.id',
            'nip' => 'NIP-0000000003',
            'role' => User::ROLE_PPK,
            'jabatan' => 'Pejabat Pembuat Komitmen',
            'id_unit' => $adum?->id,
            'id_atasan' => $pimpinan->id,
        ]);

        // Bendahara — modul keuangan beserta bukti pembayaran.
        User::factory()->create([
            'nama' => 'Bendahara Pengeluaran',
            'email' => 'bendahara@poltekkes.ac.id',
            'nip' => 'NIP-0000000004',
            'role' => User::ROLE_BENDAHARA,
            'jabatan' => 'Bendahara Pengeluaran',
            'id_unit' => $adum?->id,
            'id_atasan' => $ppk->id,
        ]);

        // Tim keuangan — input biaya, tanpa akses bukti bayar.
        User::factory()->create([
            'nama' => 'Staf Tim Keuangan',
            'email' => 'keuangan@poltekkes.ac.id',
            'nip' => 'NIP-0000000006',
            'role' => User::ROLE_TIM_KEUANGAN,
            'jabatan' => 'Pengelola Keuangan',
            'id_unit' => $adum?->id,
            'id_atasan' => $ppk->id,
        ]);

        // Tim SDM — hanya melihat siapa yang akan berangkat.
        User::factory()->create([
            'nama' => 'Staf Tim SDM',
            'email' => 'sdm@poltekkes.ac.id',
            'nip' => 'NIP-0000000005',
            'role' => User::ROLE_TIM_SDM,
            'jabatan' => 'Analis Kepegawaian',
            'id_unit' => $adum?->id,
            'id_atasan' => $pimpinan->id,
        ]);

        // Pengusul non-internal.
        User::factory()->create([
            'nama' => 'Pegawai Kemenkes Pusat',
            'email' => 'eksternal@kemkes.go.id',
            'nip' => 'NIP-0000000007',
            'role' => User::ROLE_PEGAWAI_EKSTERNAL,
            'jabatan' => 'Analis Program',
            'id_unit' => $adum?->id,
            'id_atasan' => $pimpinan->id,
        ]);

        User::factory()->create([
            'nama' => 'Petugas Outsourcing',
            'email' => 'outsourcing@poltekkes.ac.id',
            'nip' => 'NIP-0000000008',
            'role' => User::ROLE_OUTSOURCING,
            'jabatan' => 'Pengemudi',
            'id_unit' => $adum?->id,
            'id_atasan' => $ppk->id,
        ]);

        // Setiap jurusan dipimpin seorang ketua yang menjadi atasan langsung stafnya.
        foreach ($jurusan as $unit) {
            $ketua = User::factory()->create([
                'nama' => 'Ketua '.$unit->nama,
                'role' => User::ROLE_DOSEN_TENDIK,
                'jabatan' => 'Ketua Jurusan',
                'id_unit' => $unit->id,
                'id_atasan' => $pimpinan->id,
            ]);

            User::factory()
                ->count(2)
                ->diUnit($unit)
                ->berAtasan($ketua)
                ->create();
        }
    }
}
