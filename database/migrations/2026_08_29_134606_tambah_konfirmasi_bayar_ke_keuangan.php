<?php

use App\Models\Keuangan;
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
        Schema::table('keuangan', function (Blueprint $table) {
            // Kode publik pada QR konfirmasi bendahara, terbit saat pelunasan.
            $table->string('kode_konfirmasi_bayar', 32)->nullable()->unique()->after('status');
            $table->timestamp('dikonfirmasi_bayar_at')->nullable()->after('kode_konfirmasi_bayar');
        });

        // Pembayaran yang sudah lunas sebelum fitur ini ada tetap perlu kode,
        // supaya QR-nya ikut tercetak pada dokumen rincian biayanya.
        Keuangan::query()
            ->where('status', Keuangan::STATUS_LUNAS)
            ->whereNull('kode_konfirmasi_bayar')
            ->get()
            ->each(fn (Keuangan $keuangan) => $keuangan->forceFill([
                'kode_konfirmasi_bayar' => Keuangan::buatKodeKonfirmasiBayar(),
                'dikonfirmasi_bayar_at' => $keuangan->tanggal_pelunasan ?? $keuangan->updated_at ?? now(),
            ])->save());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keuangan', function (Blueprint $table) {
            $table->dropColumn(['kode_konfirmasi_bayar', 'dikonfirmasi_bayar_at']);
        });
    }
};
