<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiket keberangkatan dan kepulangan, masing-masing satu baris.
 *
 * Dipisah dari tabel dokumen karena tiap arah punya rute, nomor, kode booking,
 * harga, dan boarding pass sendiri — bila dijadikan kolom semuanya, tabel
 * dokumen bertambah selusin kolom yang setengahnya selalu kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiket_perjadin', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_usulan')->constrained('usulan')->cascadeOnDelete();

            // 'pergi' atau 'pulang'.
            $table->string('arah', 10);

            $table->string('kota_asal')->nullable();
            $table->string('kota_tujuan')->nullable();
            $table->string('nomor_tiket')->nullable();
            $table->string('kode_booking')->nullable();

            // Harga sudah termasuk pajak, sesuai yang tertera pada tiket.
            $table->decimal('harga', 15, 2)->nullable();

            $table->string('boarding_pass')->nullable();

            $table->timestamps();

            // Satu tiket per arah per usulan; pengiriman ulang formulir
            // memperbarui barisnya, bukan menumpuk baris baru.
            $table->unique(['id_usulan', 'arah']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_perjadin');
    }
};
