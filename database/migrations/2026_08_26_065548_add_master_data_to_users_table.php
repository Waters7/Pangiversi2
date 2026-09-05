<?php

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
        Schema::table('users', function (Blueprint $table) {
            $table->string('jabatan')->nullable()->after('role');
            $table->foreignId('id_unit')->nullable()->after('jabatan')->constrained('unit_kerja')->nullOnDelete();
            $table->foreignId('id_atasan')->nullable()->after('id_unit')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_atasan');
            $table->dropConstrainedForeignId('id_unit');
            $table->dropColumn('jabatan');
        });
    }
};
