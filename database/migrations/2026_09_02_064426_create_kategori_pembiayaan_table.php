<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori pembiayaan perjalanan dinas — sumber dana yang membiayainya.
 *
 * Daftar nominatif dipisah per kategori ini, persis seperti lembar LN, BLU,
 * dan RM pada berkas rekap yang selama ini disusun tim keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_pembiayaan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        $bawaan = [
            ['kode' => 'RM', 'nama' => 'Rupiah Murni', 'urutan' => 1],
            ['kode' => 'BLU', 'nama' => 'Badan Layanan Umum', 'urutan' => 2],
            ['kode' => 'LN', 'nama' => 'Luar Negeri', 'urutan' => 3],
        ];

        foreach ($bawaan as $baris) {
            DB::table('kategori_pembiayaan')->insert($baris + [
                'is_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_pembiayaan');
    }
};
