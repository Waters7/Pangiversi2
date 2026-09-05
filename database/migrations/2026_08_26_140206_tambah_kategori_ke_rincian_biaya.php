<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            // Kategori baku Lampiran II PMK 113/PMK.05/2012.
            $table->enum('kategori', ['transport', 'uang_harian', 'transport_lokal', 'penginapan', 'lainnya'])
                ->default('lainnya')
                ->after('id');
            $table->string('keterangan')->nullable()->after('jumlah');
        });

        // Tebak kategori data lama dari nama komponennya.
        $tebakan = [
            'transport_lokal' => ['%lokal%'],
            'transport' => ['%tiket%', '%transport%', '%pesawat%', '%bandara%'],
            'uang_harian' => ['%harian%', '%saku%', '%representasi%'],
            'penginapan' => ['%pengina%', '%hotel%'],
        ];

        foreach ($tebakan as $kategori => $polaList) {
            foreach ($polaList as $pola) {
                DB::table('rincian_biayas')
                    ->where('kategori', 'lainnya')
                    ->whereRaw('LOWER(komponen) LIKE ?', [$pola])
                    ->update(['kategori' => $kategori]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'keterangan']);
        });
    }
};
