<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isian berkas pelaksana yang diwakili baris tulisan tim keuangan.
     *
     * Satu komponen — tiket pergi, tiket pulang, bill hotel, biaya
     * penyelenggaraan — hanya boleh dinominalkan satu pihak. Kolom ini
     * mencatat isian mana yang sudah ditetapkan tim keuangan, sehingga isian
     * pelaksana untuk komponen itu terkunci dan tidak tercatat dua kali.
     */
    public function up(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->string('isian_pelaksana', 30)->nullable()->after('kunci_sumber');
        });

        // Baris penginapan dan penyelenggaraan tulisan tim keuangan yang sudah
        // ada langsung mewakili isian pelaksananya — kecuali bila pelaksana
        // telanjur mengisi komponen yang sama: data lama seperti itu dibiarkan
        // untuk diperiksa tim keuangan, bukan dipilihkan salah satunya.
        // Baris transport lama tidak ditebak tiketnya.
        foreach (['penginapan' => 'hotel', 'penyelenggaraan' => 'penyelenggaraan'] as $kategori => $isian) {
            $sudahDiisiPelaksana = DB::table('rincian_biayas')
                ->where('sumber', 'dokumen')
                ->where('kunci_sumber', $isian)
                ->pluck('id_keuangan')
                ->all();

            DB::table('rincian_biayas')
                ->where('sumber', 'keuangan')
                ->where('kategori', $kategori)
                ->whereNotIn('id_keuangan', $sudahDiisiPelaksana)
                ->update(['isian_pelaksana' => $isian]);
        }
    }

    public function down(): void
    {
        Schema::table('rincian_biayas', function (Blueprint $table) {
            $table->dropColumn('isian_pelaksana');
        });
    }
};
