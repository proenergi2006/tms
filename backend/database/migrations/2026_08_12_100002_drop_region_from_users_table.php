<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lihat catatan pada migrasi kembarannya
     * 2026_08_12_100001_drop_region_from_branches_table — fleet_operations
     * sekarang branch-scoped (bukan region-scoped) sama seperti
     * sa/kepala_pool/tim_logistik, dan leader_operations dihapus total dari
     * sistem, jadi kolom ini tidak dipakai lagi oleh siapa pun.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('region', ['barat', 'timur'])->nullable()->after('branch_id');
        });
    }
};
