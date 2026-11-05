<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tim keuangan dan pimpinan kini menelusuri seluruh perjalanan dari menu
 * Dokumen Perdin. Peran yang hak aksesnya sudah tersimpan diberi haknya di
 * sini; yang belum pernah disimpan memakai bawaan enumnya, yang sudah memuatnya.
 */
return new class extends Migration
{
    private const KEMAMPUAN = 'melihat-semua-dokumen';

    private const PERAN = ['tim_keuangan', 'pimpinan'];

    public function up(): void
    {
        $peran = DB::table('peran')->whereIn('kode', self::PERAN)->pluck('id');

        DB::table('hak_akses_peran')->insertOrIgnore($peran->map(fn ($id) => [
            'id_peran' => $id,
            'kemampuan' => self::KEMAMPUAN,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        DB::table('hak_akses_peran')
            ->where('kemampuan', self::KEMAMPUAN)
            ->whereIn('id_peran', DB::table('peran')->whereIn('kode', self::PERAN)->pluck('id'))
            ->delete();
    }
};
