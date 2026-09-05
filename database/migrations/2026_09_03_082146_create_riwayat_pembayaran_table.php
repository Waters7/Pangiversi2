<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnal pembayaran: satu baris untuk tiap tindakan bendahara.
 *
 * Tabel `keuangan` menyimpan keadaan terakhir sebuah perjalanan — sudah
 * dibayar berapa, lunas atau belum. Tabel ini menyimpan riwayatnya: siapa
 * membayar, kapan, berapa, dan dengan bukti apa. Keduanya berbeda tugas,
 * jadi tidak saling menggantikan; pembatalan pun ikut tercatat di sini
 * ketimbang menghapus jejaknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pembayaran', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_keuangan')->constrained('keuangan')->cascadeOnDelete();
            $table->foreignId('id_usulan')->constrained('usulan')->cascadeOnDelete();

            // uang_muka | pelunasan | batal_uang_muka | batal_pelunasan
            $table->string('jenis', 32);
            $table->decimal('nominal', 15, 2)->default(0);
            $table->date('tanggal');

            $table->foreignId('id_pencatat')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bukti')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['tanggal', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pembayaran');
    }
};
