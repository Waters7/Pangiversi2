<?php

namespace Database\Factories;

use App\Models\Dokumen;
use App\Models\Usulan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dokumen>
 */
class DokumenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $makeFile = function (string $folder) {
            $path = 'dokumen/'.$folder.'/'.Str::uuid().'.pdf';
            Storage::disk('public')->put($path, fake()->text());

            return $path;
        };

        $optional = function (string $folder) use ($makeFile) {
            return fake()->boolean(50) ? $makeFile($folder) : null;
        };

        return [
            'surat_tugas' => $makeFile('surat-tugas'),
            'rundown' => $optional('rundown'),
            'dokumen_pendukung' => $optional('dokumen-pendukung'),
            'sppd' => $optional('sppd'),
            'boarding_pass' => $optional('boarding-pass'),
            'faktur' => $optional('faktur'),
            'kwintasi' => $optional('kwintasi'),
            'bill_hotel' => $optional('bill-hotel'),
            'laporan_hasil' => $optional('laporan-hasil'),
            'id_usulan' => Usulan::inRandomOrder()->first()->id,
        ];
    }
}
