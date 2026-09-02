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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->timestamp('fleet_ops_verified_at')->nullable()->after('approval_status')
                ->comment('Verifikasi akhir Fleet Operations atas laporan kegiatan mekanik — gerbang tambahan sebelum WO dicatat final (lihat WorkOrderCompletionService).');
            $table->foreignId('fleet_ops_verified_by')->nullable()->after('fleet_ops_verified_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fleet_ops_verified_by');
            $table->dropColumn('fleet_ops_verified_at');
        });
    }
};
