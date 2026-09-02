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
            // Nullable: user yang hanya login lewat SSO (begitu tersedia)
            // tidak wajib punya password TMS. Ditambahkan karena tidak semua
            // pengguna (driver/mekanik cabang, dsb) punya akun SYOP untuk SSO
            // — mereka butuh jalur login kredensial TMS sendiri.
            $table->string('password')->nullable()->after('sso_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
