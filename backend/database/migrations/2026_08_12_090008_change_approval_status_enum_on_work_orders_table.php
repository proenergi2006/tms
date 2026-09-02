<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Approval collapse jadi satu tahap: submitted -> completed/rejected
     * (lihat ApprovalWorkflowService::currentStage()). Tahap perantara lama
     * (pool_verified/bm_verified/logistik_verified/finance_approved) semua
     * berarti "sudah melewati sedikitnya tahap Kepala Pool lama" — dianggap
     * paling dekat dengan 'completed' pada model baru (WO ini sudah pernah
     * mendapat approval, cuma belum tuntas seluruh rantai lama). Widen dulu
     * supaya baris lama tidak truncate, backfill, baru persempit ke enum
     * final ['submitted', 'completed', 'rejected'].
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE work_orders MODIFY COLUMN approval_status ENUM('submitted', 'pool_verified', 'bm_verified', 'logistik_verified', 'finance_approved', 'completed', 'rejected') NOT NULL DEFAULT 'submitted'");

        DB::table('work_orders')
            ->whereIn('approval_status', ['pool_verified', 'bm_verified', 'logistik_verified', 'finance_approved'])
            ->update(['approval_status' => 'completed']);

        DB::statement("ALTER TABLE work_orders MODIFY COLUMN approval_status ENUM('submitted', 'completed', 'rejected') NOT NULL DEFAULT 'submitted' COMMENT 'State machine approval satu tahap: submitted -> completed/rejected.'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE work_orders MODIFY COLUMN approval_status ENUM('submitted', 'pool_verified', 'bm_verified', 'logistik_verified', 'finance_approved', 'completed', 'rejected') NOT NULL DEFAULT 'submitted' COMMENT 'State machine approval, lihat Design Document 4.2'");
    }
};
