<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tim kerja pengelola anggaran dan pagunya per kode akun perjalanan dinas,
 * serta tim kerja yang dibebani pada tiap daftar nominatif.
 *
 * Pagu TA 2026 diisi dari Daftar Anggaran Fix TA 2026 (dicetak 30 Oktober
 * 2026, lembar "Anggaran Fix", baris berstatus Fix) untuk tiga akun yang
 * berpagu: 524111, 524119, dan 525115. Kewadiran dicatat seperti pada
 * lembar itu; Satuan Pengawas Internal tidak berada di bawah kewadiran.
 */
return new class extends Migration
{
    private const TAHUN = 2026;

    /** @var array<string, ?string> Nama tim kerja => kewadirannya. */
    private const TIM_KERJA = [
        'Pusat Penjaminan Mutu' => 'Kewadiran I',
        'Pusat Penelitian dan Pengabdian Masyarakat' => 'Kewadiran I',
        'Tim Administrasi Akademik' => 'Kewadiran I',
        'Unit Lab Terpadu' => 'Kewadiran I',
        'Farmasi' => 'Kewadiran I',
        'Gizi' => 'Kewadiran I',
        'Kebidanan' => 'Kewadiran I',
        'Keperawatan' => 'Kewadiran I',
        'Kesehatan Gigi' => 'Kewadiran I',
        'Kesehatan Lingkungan' => 'Kewadiran I',
        'Teknologi Laboratorium Medis' => 'Kewadiran I',
        'Tim Keuangan dan Barang Milik Negara' => 'Kewadiran II',
        'Tim OSDM' => 'Kewadiran II',
        'Tim Perencanaan dan Evaluasi' => 'Kewadiran II',
        'Unit Bisnis' => 'Kewadiran II',
        'Tim Kemahasiswaan dan Alumni' => 'Kewadiran III',
        'Tim Umum dan Hubungan Masyarakat serta Kerjasama' => 'Kewadiran III',
        'Satuan Pengawas Internal' => null,
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: int}> Kode akun, sumber dana, tim kerja, pagu. */
    private const PAGU = [
        ['524111', 'RM', 'Pusat Penjaminan Mutu', 41_564_000],
        ['524111', 'RM', 'Satuan Pengawas Internal', 33_804_000],
        ['524111', 'RM', 'Tim Administrasi Akademik', 5_460_000],
        ['524111', 'RM', 'Tim Kemahasiswaan dan Alumni', 163_305_000],
        ['524111', 'RM', 'Tim Keuangan dan Barang Milik Negara', 115_056_000],
        ['524111', 'RM', 'Tim OSDM', 69_191_000],
        ['524111', 'RM', 'Unit Lab Terpadu', 8_752_000],
        ['524119', 'RM', 'Tim Perencanaan dan Evaluasi', 144_048_000],
        ['525115', 'BLU', 'Farmasi', 24_300_000],
        ['525115', 'BLU', 'Gizi', 11_250_000],
        ['525115', 'BLU', 'Kebidanan', 26_400_000],
        ['525115', 'BLU', 'Keperawatan', 59_670_000],
        ['525115', 'BLU', 'Kesehatan Gigi', 2_543_000],
        ['525115', 'BLU', 'Kesehatan Lingkungan', 37_620_000],
        ['525115', 'BLU', 'Pusat Penelitian dan Pengabdian Masyarakat', 217_610_000],
        ['525115', 'BLU', 'Satuan Pengawas Internal', 60_836_000],
        ['525115', 'BLU', 'Teknologi Laboratorium Medis', 25_410_000],
        ['525115', 'BLU', 'Tim Administrasi Akademik', 72_770_000],
        ['525115', 'BLU', 'Tim Kemahasiswaan dan Alumni', 176_150_000],
        ['525115', 'BLU', 'Tim Perencanaan dan Evaluasi', 510_479_000],
        ['525115', 'BLU', 'Tim Umum dan Hubungan Masyarakat serta Kerjasama', 365_506_000],
        ['525115', 'BLU', 'Unit Bisnis', 52_228_000],
        ['525115', 'RM', 'Tim Kemahasiswaan dan Alumni', 254_582_000],
    ];

    public function up(): void
    {
        Schema::create('tim_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->unique();
            $table->string('kewadiran', 60)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('pagu_tim_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_tahun_anggaran')->constrained('tahun_anggaran')->cascadeOnDelete();
            $table->foreignId('id_akun_pembiayaan')->constrained('akun_pembiayaan')->cascadeOnDelete();
            $table->foreignId('id_kategori_pembiayaan')->nullable()->constrained('kategori_pembiayaan')->nullOnDelete();
            $table->foreignId('id_tim_kerja')->constrained('tim_kerja')->cascadeOnDelete();
            $table->decimal('pagu', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['id_tahun_anggaran', 'id_akun_pembiayaan', 'id_kategori_pembiayaan', 'id_tim_kerja'], 'pagu_tim_kerja_unik');
        });

        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->foreignId('id_tim_kerja')->nullable()->after('id_akun_pembiayaan')
                ->constrained('tim_kerja')->nullOnDelete();
        });

        $this->isiPaguTimKerja();
    }

    /**
     * Tim kerja dan pagunya pada TA 2026. Dilewati bila tahun anggarannya
     * belum ada, seperti pada pemasangan baru.
     */
    public function isiPaguTimKerja(): void
    {
        $tahun = DB::table('tahun_anggaran')->where('tahun', self::TAHUN)->value('id');

        if (! $tahun) {
            return;
        }

        $urutan = 0;
        foreach (self::TIM_KERJA as $nama => $kewadiran) {
            DB::table('tim_kerja')->updateOrInsert(
                ['nama' => $nama],
                ['kewadiran' => $kewadiran, 'urutan' => ++$urutan, 'is_aktif' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $tim = DB::table('tim_kerja')->pluck('id', 'nama');
        $akun = DB::table('akun_pembiayaan')->pluck('id', 'kode');
        $sumber = DB::table('kategori_pembiayaan')->pluck('id', 'kode');

        foreach (self::PAGU as [$kode, $sumberDana, $namaTim, $pagu]) {
            if (! isset($akun[$kode], $tim[$namaTim])) {
                continue;
            }

            DB::table('pagu_tim_kerja')->updateOrInsert(
                [
                    'id_tahun_anggaran' => $tahun,
                    'id_akun_pembiayaan' => $akun[$kode],
                    'id_kategori_pembiayaan' => $sumber[$sumberDana] ?? null,
                    'id_tim_kerja' => $tim[$namaTim],
                ],
                ['pagu' => $pagu, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::table('daftar_nominatif', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_tim_kerja');
        });

        Schema::dropIfExists('pagu_tim_kerja');
        Schema::dropIfExists('tim_kerja');
    }
};
