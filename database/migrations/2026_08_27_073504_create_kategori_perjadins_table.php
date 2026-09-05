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
        // Kategori perjalanan dinas menentukan kelas biayanya — fullboard,
        // fullday, halfday, dan seterusnya — terpisah dari jenis kegiatannya.
        Schema::create('kategori_perjadin', function (Blueprint $table) {
            $table->id();
            $table->string('grup', 64);
            $table->string('nama', 128);
            $table->string('kode', 32)->unique();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            $table->index(['grup', 'urutan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_perjadin');
    }
};
