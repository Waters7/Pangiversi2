<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiap tiket pesawat — pergi maupun pulang — membawa invoice pembeliannya
 * sendiri, di samping boarding pass. Boarding pass membuktikan perjalanan
 * terjadi; invoice membuktikan harganya, dan harga itulah yang diganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiket_perjadin', function (Blueprint $table) {
            $table->string('invoice')->nullable()->after('boarding_pass');
        });
    }

    public function down(): void
    {
        Schema::table('tiket_perjadin', function (Blueprint $table) {
            $table->dropColumn('invoice');
        });
    }
};
