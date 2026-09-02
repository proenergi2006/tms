<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap cabang sekarang punya SA, Fleet Operations, DAN Kepala Pool
     * sendiri — approval chain seragam per cabang lewat approval_steps
     * (lihat migrasi create_approval_steps_table & ApprovalWorkflowService),
     * jadi alasan region (barat/timur) ada sejak awal (fallback approval ke
     * Leader Operations untuk cabang tanpa Kepala Pool, dan pembagian
     * wilayah verifikasi Fleet Operations) sudah tidak berlaku lagi. Lihat
     * juga migrasi kembarannya pada tabel users.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->enum('region', ['barat', 'timur'])->nullable()->after('code');
        });
    }
};
