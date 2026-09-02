<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menunjuk tahap approval_steps yang SEDANG menunggu untuk Work Order
     * ini — null berarti approval_status sudah completed/rejected (lihat
     * ApprovalWorkflowService). nullOnDelete supaya penghapusan tahap (yang
     * mestinya tidak pernah terjadi lewat UI — ApprovalStepController sengaja
     * tidak punya destroy()) tidak merusak WO yang masih merujuknya.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('approval_step_id')->nullable()->after('approval_status')
                ->constrained('approval_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_step_id');
        });
    }
};
