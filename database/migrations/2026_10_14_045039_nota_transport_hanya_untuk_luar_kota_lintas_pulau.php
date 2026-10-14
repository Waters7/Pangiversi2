<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nota / bukti biaya transportasi kini hanya ditagih pada perjalanan luar
 * kota lintas pulau. Jalur lain yang susunan berkasnya sudah pernah diatur
 * lewat Administrasi Sistem → Berkas Pertanggungjawaban dilepaskan dari nota
 * transport; jalur yang belum diatur mengikuti susunan bawaan yang baru.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const JALUR = ['dalam-kota', 'luar-kota-provinsi', 'supervisi'];

    public function up(): void
    {
        foreach (self::JALUR as $jalur) {
            $baris = DB::table('pengaturan')->where('kunci', 'berkas_lpj_'.$jalur)->first();
            $berkas = json_decode((string) $baris?->nilai, true);

            if (! is_array($berkas) || ! in_array('nota_transport', $berkas, true)) {
                continue;
            }

            DB::table('pengaturan')->where('kunci', 'berkas_lpj_'.$jalur)->update([
                'nilai' => json_encode(array_values(array_diff($berkas, ['nota_transport']))),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('pengaturan.semua');
    }

    public function down(): void
    {
        // Pengaturan yang dilepaskan tidak dipulihkan; administrator dapat
        // mencentangnya kembali pada Berkas Pertanggungjawaban.
    }
};
