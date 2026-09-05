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
        Schema::table('peserta_usulan', function (Blueprint $table) {
            // Peserta yang didaftarkan orang lain harus mengonfirmasi keikutsertaannya
            // sebelum usulan divalidasi PPK.
            $table->enum('konfirmasi', ['menunggu', 'dikonfirmasi', 'dibatalkan'])
                ->default('dikonfirmasi')
                ->after('peran');
            $table->text('alasan_batal')->nullable()->after('konfirmasi');
            $table->timestamp('dikonfirmasi_at')->nullable()->after('alasan_batal');
        });

        // Data lama berasal dari pengajuan personal, jadi dianggap sudah dikonfirmasi.
        DB::table('peserta_usulan')->update(['dikonfirmasi_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peserta_usulan', function (Blueprint $table) {
            $table->dropColumn(['konfirmasi', 'alasan_batal', 'dikonfirmasi_at']);
        });
    }
};
