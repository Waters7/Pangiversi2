<?php

namespace Database\Seeders;

use App\Models\PesertaUsulan;
use App\Models\Usulan;
use Illuminate\Database\Seeder;

class PesertaUsulanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Usulan::with('user')->each(function (Usulan $usulan): void {
            if (! $usulan->user) {
                return;
            }

            // Pengusul selalu tercatat sebagai ketua tim.
            PesertaUsulan::updateOrCreate(
                ['id_usulan' => $usulan->id, 'id_user' => $usulan->user->id],
                [
                    'nama' => $usulan->user->nama,
                    'nip' => $usulan->user->nip,
                    'jabatan' => $usulan->user->jabatan,
                    'peran' => 'ketua',
                ]
            );
        });
    }
}
