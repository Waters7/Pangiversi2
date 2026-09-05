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
        Schema::create('persetujuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usulan')->constrained('usulan')->cascadeOnDelete();
            $table->foreignId('id_approver')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('peran');
            $table->enum('keputusan', ['setuju', 'tolak', 'revisi']);
            $table->text('catatan')->nullable();
            $table->timestamp('waktu_keputusan');
            $table->timestamps();

            $table->index(['id_usulan', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persetujuan');
    }
};
