<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota transportasi lokal: empat ruas tetap dari rumah sampai kembali ke rumah.
 *
 * Ruasnya disimpan sebagai baris bernomor, bukan empat set kolom, supaya
 * penjumlahan totalnya cukup satu SUM dan urutan cetaknya tidak perlu
 * ditentukan ulang di setiap tempat yang menampilkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_transport', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_usulan')->constrained('usulan')->cascadeOnDelete();

            // 1..4, mengikuti urutan ruas pada App\Enums\RuasTransport.
            $table->unsignedTinyInteger('urutan');

            $table->decimal('nominal', 15, 2)->nullable();
            $table->string('keterangan')->nullable();
            $table->string('bukti')->nullable();

            $table->timestamps();

            $table->unique(['id_usulan', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_transport');
    }
};
