<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian pada berkas pertanggungjawaban yang selama ini hanya berupa unggahan.
 *
 * Bill hotel kini disertai nomor transaksi dan nominalnya supaya angkanya bisa
 * disandingkan dengan rincian biaya, bukan hanya tersimpan sebagai gambar yang
 * harus dibaca ulang tim keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->string('bill_hotel_no_transaksi')->nullable()->after('bill_hotel');
            $table->decimal('bill_hotel_nominal', 15, 2)->nullable()->after('bill_hotel_no_transaksi');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen', function (Blueprint $table): void {
            $table->dropColumn(['bill_hotel_no_transaksi', 'bill_hotel_nominal']);
        });
    }
};
