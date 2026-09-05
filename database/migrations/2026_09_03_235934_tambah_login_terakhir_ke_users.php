<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapan seorang pengguna terakhir masuk, dan dari alamat mana.
 *
 * Berguna bagi administrator untuk mengenali akun yang tidak pernah dipakai
 * dan untuk menelusuri masuknya seseorang saat ada persoalan. Berbeda dari
 * "sedang aktif", yang dibaca dari tabel sesi dan berumur pendek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('login_terakhir_at')->nullable()->after('remember_token');
            $table->string('login_terakhir_ip', 45)->nullable()->after('login_terakhir_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['login_terakhir_at', 'login_terakhir_ip']);
        });
    }
};
