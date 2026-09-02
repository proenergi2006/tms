<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * requests.status tetap dijaga selaras persis dengan
     * work_orders.approval_status oleh ApprovalWorkflowService (lihat
     * approve()/reject()) — enum & mapping backfill di sini sengaja sama
     * dengan migrasi 2026_08_12_090008_change_approval_status_enum_on_work_orders_table.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('submitted', 'pool_verified', 'bm_verified', 'logistik_verified', 'finance_approved', 'completed', 'rejected') NOT NULL DEFAULT 'submitted'");

        DB::table('requests')
            ->whereIn('status', ['pool_verified', 'bm_verified', 'logistik_verified', 'finance_approved'])
            ->update(['status' => 'completed']);

        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('submitted', 'completed', 'rejected') NOT NULL DEFAULT 'submitted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM('submitted', 'pool_verified', 'bm_verified', 'logistik_verified', 'finance_approved', 'completed', 'rejected') NOT NULL DEFAULT 'submitted'");
    }
};
