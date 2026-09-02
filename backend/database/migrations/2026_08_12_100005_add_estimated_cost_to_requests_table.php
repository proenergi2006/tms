<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estimasi Biaya Perbaikan — diisi SA di form pengajuan saat
     * type=perbaikan (lihat StoreRequestRequest). Murni estimasi/rencana,
     * BUKAN biaya realisasi final (itu dihitung dari work_order_items yang
     * di-realize lewat WorkOrderController::realizeItems()).
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->decimal('estimated_cost', 15, 2)->nullable()->after('diagnosis')
                ->comment('Estimasi biaya perbaikan, wajib diisi SA untuk type=perbaikan.');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('estimated_cost');
        });
    }
};
