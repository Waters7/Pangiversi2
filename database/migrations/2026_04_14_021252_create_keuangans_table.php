<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('keuangan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_transfer')->nullable();
            $table->date('tanggal_pelunasan')->nullable();
            $table->float('total')->default(0);
            $table->float('uang_muka')->default(0);
            $table->float('sisa')->default(0);
            $table->enum('status', ['bayar sebagian', 'lunas', 'belum bayar'])->default('belum bayar');
            $table->foreignId('id_usulan')->constrained('usulan')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan');
    }
};
