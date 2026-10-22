<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan "Pangkat dan Golongan" sebagai master data, supaya satuan kerja
 * dapat menambahkannya sendiri — misalnya tenaga outsourcing — tanpa
 * menunggu penempatan ulang aplikasi.
 *
 * Kodenya yang disimpan pada kolom golongan pengguna (paling panjang
 * delapan karakter, mengikuti kolom itu); tulisannya yang tercetak pada
 * Surat Perjalanan Dinas.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PNS = [
        'IV/e' => 'Pembina Utama',
        'IV/d' => 'Pembina Utama Madya',
        'IV/c' => 'Pembina Utama Muda',
        'IV/b' => 'Pembina Tingkat I',
        'IV/a' => 'Pembina',
        'III/d' => 'Penata Tingkat I',
        'III/c' => 'Penata',
        'III/b' => 'Penata Muda Tingkat I',
        'III/a' => 'Penata Muda',
        'II/d' => 'Pengatur Tingkat I',
        'II/c' => 'Pengatur',
        'II/b' => 'Pengatur Muda Tingkat I',
        'II/a' => 'Pengatur Muda',
        'I/d' => 'Juru Tingkat I',
        'I/c' => 'Juru',
        'I/b' => 'Juru Muda Tingkat I',
        'I/a' => 'Juru Muda',
    ];

    /** @var list<string> */
    private const PPPK = ['XVII', 'XVI', 'XV', 'XIV', 'XIII', 'XII', 'XI', 'X', 'IX', 'VIII', 'VII', 'VI', 'V', 'IV', 'III', 'II', 'I'];

    /** @var array<string, string> */
    private const KELOMPOK_PNS = [
        'IV' => 'Golongan IV (Pembina)',
        'III' => 'Golongan III (Penata)',
        'II' => 'Golongan II (Pengatur)',
        'I' => 'Golongan I (Juru)',
    ];

    public function up(): void
    {
        Schema::create('pangkat_golongan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode', 8)->unique();
            $table->string('tulisan', 100);
            $table->string('kelompok', 60);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
        });

        $baris = [];

        foreach (self::PNS as $kode => $pangkat) {
            $baris[] = ['kode' => $kode, 'tulisan' => "{$pangkat} ({$kode})", 'kelompok' => self::KELOMPOK_PNS[strstr($kode, '/', true)]];
        }

        foreach (self::PPPK as $kode) {
            $baris[] = ['kode' => $kode, 'tulisan' => "Golongan {$kode} (PPPK)", 'kelompok' => 'PPPK (Golongan I–XVII)'];
        }

        $baris[] = ['kode' => 'OS', 'tulisan' => 'Outsourcing', 'kelompok' => 'Non-ASN'];

        foreach ($baris as $urutan => $isi) {
            DB::table('pangkat_golongan')->insert($isi + [
                'urutan' => $urutan + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pangkat_golongan');
    }
};
