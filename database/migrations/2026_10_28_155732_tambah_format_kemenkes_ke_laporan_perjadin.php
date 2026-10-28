<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Format laporan perjalanan dinas Kemenkes: judul "tentang", Pendahuluan
 * (umum, maksud dan tujuan, ruang lingkup, dasar), kegiatan yang
 * dilaksanakan, hasil yang dicapai, simpulan dan saran, serta penutup —
 * masing-masing boleh dilengkapi satu foto dokumentasi yang tercetak pada
 * halaman lampiran.
 *
 * Laporan yang sudah diisi dengan format lama tetap memakai format lama
 * (versi 1); laporan baru dan yang belum diisi apa pun memakai format ini
 * (versi 2). Kolom dasar, hasil, dan kesimpulan yang sudah ada dipakai
 * ulang untuk bagian A.4, C, dan D.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->unsignedTinyInteger('versi_format')->default(2)->after('id_usulan');
            $table->string('tentang', 500)->nullable()->after('versi_format');
            $table->text('umum')->nullable()->after('tentang');
            $table->text('maksud_tujuan')->nullable()->after('umum');
            $table->text('ruang_lingkup')->nullable()->after('maksud_tujuan');
            $table->text('kegiatan_dilaksanakan')->nullable()->after('dasar');
            $table->text('penutup')->nullable()->after('kesimpulan');
        });

        Schema::create('laporan_dokumentasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_laporan')->constrained('laporan_perjadin')->cascadeOnDelete();
            $table->string('bagian', 30);
            $table->string('berkas');
            $table->timestamps();

            $table->unique(['id_laporan', 'bagian']);
        });

        // Laporan yang sudah berisi tetap pada format lama.
        DB::table('laporan_perjadin')
            ->where(function ($query): void {
                $query->whereNotNull('diselesaikan_at')
                    ->orWhereNotNull('id_status_hasil')
                    ->orWhere(fn ($q) => $q->whereNotNull('kesimpulan')->where('kesimpulan', '!=', ''))
                    ->orWhere(fn ($q) => $q->whereNotNull('berkas_laporan')->where('berkas_laporan', '!=', ''))
                    ->orWhereExists(fn ($q) => $q->select(DB::raw(1))->from('laporan_kegiatan')->whereColumn('laporan_kegiatan.id_laporan', 'laporan_perjadin.id'))
                    ->orWhereExists(fn ($q) => $q->select(DB::raw(1))->from('tindak_lanjut')->whereColumn('tindak_lanjut.id_laporan', 'laporan_perjadin.id'));
            })
            ->update(['versi_format' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_dokumentasi');

        Schema::table('laporan_perjadin', function (Blueprint $table) {
            $table->dropColumn(['versi_format', 'tentang', 'umum', 'maksud_tujuan', 'ruang_lingkup', 'kegiatan_dilaksanakan', 'penutup']);
        });
    }
};
