<?php

namespace Database\Seeders;

use App\Enums\StatusUsulan;
use App\Models\DaftarRiil;
use App\Models\PesertaUsulan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Menyiapkan daftar pengeluaran riil pada setiap tahap alurnya, supaya
 * masa sanggah dan tanda tangan PPK dapat langsung dicoba tanpa harus
 * menyusun datanya satu per satu.
 */
class DaftarRiilSeeder extends Seeder
{
    public function run(): void
    {
        $ppk = User::firstWhere('role', User::ROLE_PPK);

        $peserta = PesertaUsulan::with('usulan')
            ->whereHas('usulan', fn ($query) => $query->whereIn('status', [
                StatusUsulan::Disetujui->value,
                StatusUsulan::Selesai->value,
            ]))
            ->whereNotNull('id_user')
            ->get();

        if ($peserta->isEmpty()) {
            return;
        }

        // Tiap tahap diberi contohnya sendiri agar seluruh tampilan teruji.
        foreach ($peserta->values() as $urutan => $item) {
            $daftar = DaftarRiil::updateOrCreate(
                ['id_peserta' => $item->id],
                [
                    'id_usulan' => $item->id_usulan,
                    'total_riil' => fake()->numberBetween(6, 40) * 50_000,
                    'keterangan' => fake()->optional()->sentence(),
                ]
            );

            $tahap = $urutan % 4;

            // Tahap 0 dibiarkan apa adanya: baru disusun, belum dikirim.
            if ($tahap >= 1) {
                $daftar->kirimKePegawai();
            }

            if ($tahap === 2) {
                $daftar->setujuiPegawai();

                // Sebagian langsung ditandatangani PPK supaya kode verifikasi
                // dan QR-nya ada yang bisa dipindai.
                if ($ppk && $urutan % 8 === 2) {
                    $daftar->tandaTangani($ppk);
                }
            }

            if ($tahap === 3) {
                $daftar->sanggah('Uang harian hari terakhir sepertinya belum dihitung.');
            }
        }
    }
}
