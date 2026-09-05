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
        Schema::table('usulan', function (Blueprint $table) {
            // Jejak pengingat kelengkapan berkas, supaya notifikasinya tidak
            // terkirim berulang pada hari yang sama.
            $table->timestamp('pengingat_terakhir_at')->nullable()->after('dikonfirmasi_at');
            $table->unsignedTinyInteger('pengingat_terkirim')->default(0)->after('pengingat_terakhir_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            $table->dropColumn(['pengingat_terakhir_at', 'pengingat_terkirim']);
        });
    }
};
