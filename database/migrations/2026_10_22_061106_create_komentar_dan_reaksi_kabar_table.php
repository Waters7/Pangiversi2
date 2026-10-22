<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar dan reaksi pada kartu news feed dashboard eksekutif.
 *
 * Kartunya berupa perjalanan dinas (usulan) atau tindak lanjut, jadi yang
 * disimpan adalah jenis kartunya beserta id baris asalnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kabar_komentar', function (Blueprint $table): void {
            $table->id();
            $table->string('jenis', 20);
            $table->unsignedBigInteger('id_kabar');
            $table->foreignId('id_user')->constrained('users')->cascadeOnDelete();
            $table->text('isi');
            $table->timestamps();

            $table->index(['jenis', 'id_kabar']);
        });

        Schema::create('kabar_reaksi', function (Blueprint $table): void {
            $table->id();
            $table->string('jenis', 20);
            $table->unsignedBigInteger('id_kabar');
            $table->foreignId('id_user')->constrained('users')->cascadeOnDelete();
            $table->string('reaksi', 20);
            $table->timestamps();

            // Satu pengguna satu reaksi per kartu.
            $table->unique(['jenis', 'id_kabar', 'id_user']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kabar_reaksi');
        Schema::dropIfExists('kabar_komentar');
    }
};
