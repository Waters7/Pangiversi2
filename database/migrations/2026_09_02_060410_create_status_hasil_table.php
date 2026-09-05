<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan status hasil yang dicapai pada laporan perjalanan dinas.
 *
 * Disimpan sebagai master data, bukan enum, supaya satuan kerja dapat
 * menambah pilihan sendiri tanpa menunggu penempatan ulang aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_hasil', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        // Tiga pilihan baku; sisanya ditambahkan lewat menu Master Data.
        $bawaan = [
            ['nama' => 'Selesai dikerjakan', 'urutan' => 1],
            ['nama' => 'Perlu tindak lanjut', 'urutan' => 2],
            ['nama' => 'Tidak selesai', 'urutan' => 3],
        ];

        foreach ($bawaan as $baris) {
            DB::table('status_hasil')->insert($baris + [
                'is_aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('status_hasil');
    }
};
