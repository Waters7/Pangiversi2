<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pertanggungjawaban perjalanan dinas bersifat perorangan, sehingga
     * pengajuan berkelompok hanya menjadi alat bantu input: sistem membuat
     * satu usulan bernomor sendiri untuk tiap peserta.
     *
     * Konsekuensinya, konfirmasi kesediaan berpindah dari baris peserta ke
     * usulan yang bersangkutan.
     */
    public function up(): void
    {
        Schema::table('usulan', function (Blueprint $table) {
            // Penanda satu rombongan input, murni untuk penelusuran.
            $table->string('kode_rombongan', 32)->nullable()->after('jenis_pengajuan');
            $table->foreignId('id_pembuat')->nullable()->after('id_user')->constrained('users')->nullOnDelete();
            $table->enum('konfirmasi', ['menunggu', 'dikonfirmasi', 'dibatalkan'])->default('dikonfirmasi')->after('id_pembuat');
            $table->text('alasan_batal')->nullable()->after('konfirmasi');
            $table->timestamp('dikonfirmasi_at')->nullable()->after('alasan_batal');

            $table->index('kode_rombongan');
        });

        DB::table('usulan')->update(['dikonfirmasi_at' => now()]);

        Schema::table('peserta_usulan', function (Blueprint $table) {
            $table->dropColumn(['konfirmasi', 'alasan_batal', 'dikonfirmasi_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peserta_usulan', function (Blueprint $table) {
            $table->enum('konfirmasi', ['menunggu', 'dikonfirmasi', 'dibatalkan'])->default('dikonfirmasi')->after('peran');
            $table->text('alasan_batal')->nullable()->after('konfirmasi');
            $table->timestamp('dikonfirmasi_at')->nullable()->after('alasan_batal');
        });

        Schema::table('usulan', function (Blueprint $table) {
            $table->dropIndex(['kode_rombongan']);
            $table->dropConstrainedForeignId('id_pembuat');
            $table->dropColumn(['kode_rombongan', 'konfirmasi', 'alasan_batal', 'dikonfirmasi_at']);
        });
    }
};
