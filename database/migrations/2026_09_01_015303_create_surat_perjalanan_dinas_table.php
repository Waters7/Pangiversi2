<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat Perjalanan Dinas mengikuti format baku Kementerian Keuangan.
 *
 * Satu berkas SPD dapat memuat sampai lima pelaksana. Tiap pelaksana
 * memperoleh nomor suratnya sendiri karena lembar SPD terbit per orang,
 * sementara rencana perjalanannya dipakai bersama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_perjalanan_dinas', function (Blueprint $table) {
            $table->id();

            // Boleh berdiri sendiri; bila lahir dari usulan, tautannya disimpan.
            $table->foreignId('id_usulan')->nullable()->constrained('usulan')->nullOnDelete();
            $table->foreignId('id_pembuat')->nullable()->constrained('users')->nullOnDelete();

            $table->string('dikeluarkan_di')->default('Manado');
            $table->date('tanggal_surat');

            $table->text('maksud');
            $table->string('alat_angkut');
            $table->string('tempat_berangkat');
            $table->string('tempat_tujuan');
            $table->date('tanggal_berangkat');
            $table->date('tanggal_kembali');
            $table->unsignedSmallInteger('lama_hari')->default(1);

            $table->string('instansi_pembebanan')->nullable();
            $table->string('akun_pembebanan')->nullable();
            $table->text('keterangan_lain')->nullable();

            $table->timestamps();

            $table->index('tanggal_berangkat');
        });

        Schema::create('spd_pelaksana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_spd')->constrained('surat_perjalanan_dinas')->cascadeOnDelete();
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('urutan')->default(1);
            $table->string('nomor_surat');
            $table->string('nama');
            $table->string('nip')->nullable();
            $table->string('pangkat_golongan')->nullable();
            $table->string('jabatan_instansi')->nullable();
            $table->string('tingkat_biaya')->nullable();

            $table->timestamps();

            $table->index(['id_spd', 'urutan']);
        });

        Schema::create('spd_pengikut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_spd')->constrained('surat_perjalanan_dinas')->cascadeOnDelete();

            $table->string('nama');
            $table->date('tanggal_lahir')->nullable();
            $table->string('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spd_pengikut');
        Schema::dropIfExists('spd_pelaksana');
        Schema::dropIfExists('surat_perjalanan_dinas');
    }
};
