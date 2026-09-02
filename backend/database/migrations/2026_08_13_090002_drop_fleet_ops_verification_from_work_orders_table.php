<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur "Verifikasi Fleet Operations" (gerbang audit pasca-selesai)
 * dihapus — sudah redundan sejak Fleet Operations masuk ke rantai approval
 * (tahap verifikasi di awal, lihat approval_steps), bukan lagi di akhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fleet_ops_verified_by');
            $table->dropColumn('fleet_ops_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->timestamp('fleet_ops_verified_at')->nullable()->after('approval_status');
            $table->foreignId('fleet_ops_verified_by')->nullable()->after('fleet_ops_verified_at')
                ->constrained('users')->nullOnDelete();
        });
    }
};
