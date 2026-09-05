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
        Schema::table('dokumen', function (Blueprint $table) {
            // Nota/bukti biaya transportasi selama perjalanan. Tanpa berkas ini,
            // biaya transportasi tidak dapat diganti oleh tim keuangan.
            $table->string('nota_transportasi')->nullable()->after('boarding_pass');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table) {
            $table->dropColumn('nota_transportasi');
        });
    }
};
