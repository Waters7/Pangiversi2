<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status lama yang bersifat satu tahap dipetakan ke tahap berjenjang:
     * "diajukan" menjadi antrian atasan langsung, "menunggu" menjadi antrian PPK.
     *
     * @var array<string, string>
     */
    private const PEMETAAN_MAJU = [
        'diajukan' => 'menunggu_atasan',
        'menunggu' => 'menunggu_ppk',
    ];

    /**
     * @var array<string, string>
     */
    private const PEMETAAN_MUNDUR = [
        'menunggu_atasan' => 'diajukan',
        'menunggu_sdm' => 'diajukan',
        'menunggu_ppk' => 'menunggu',
        'menunggu_direktur' => 'menunggu',
        'perlu_revisi' => 'ditolak',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enum dilepas menjadi string agar rantai status berjenjang muat
        // dan penambahan status berikutnya tidak lagi butuh perubahan skema.
        Schema::table('usulan', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });

        foreach (self::PEMETAAN_MAJU as $lama => $baru) {
            DB::table('usulan')->where('status', $lama)->update(['status' => $baru]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::PEMETAAN_MUNDUR as $baru => $lama) {
            DB::table('usulan')->where('status', $baru)->update(['status' => $lama]);
        }

        Schema::table('usulan', function (Blueprint $table) {
            $table->enum('status', ['draft', 'diajukan', 'menunggu', 'disetujui', 'ditolak', 'selesai'])
                ->default('draft')
                ->change();
        });
    }
};
