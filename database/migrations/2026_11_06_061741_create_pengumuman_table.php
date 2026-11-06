<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->text('isi');
            $table->date('mulai_tayang');
            $table->date('selesai_tayang')->nullable();
            $table->foreignId('id_pembuat')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mulai_tayang', 'selesai_tayang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
