<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi notifikasi dan uraian jejak audit menjadi teks panjang.
     *
     * Keduanya dibuat sebagai VARCHAR(255). SQLite tidak menegakkan panjang
     * itu, tetapi MySQL menolak isi yang lebih panjang dengan galat 500 —
     * pengingat berkas yang menyebut banyak kekurangan, pemberitahuan
     * bendahara beserta nominalnya, atau uraian perubahan hak akses yang
     * memuat banyak kemampuan.
     */
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->text('pesan')->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('deskripsi')->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->string('pesan')->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('deskripsi')->change();
        });
    }
};
