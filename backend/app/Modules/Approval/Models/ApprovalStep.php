<?php

namespace App\Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Approval Workflow Engine dinamis — daftar tahap approval TERURUT
 * (sequence_order) yang dibaca ApprovalWorkflowService, menggantikan
 * kondisional role hardcoded (kepala_pool/leader_operations dkk) yang dulu
 * ada. Dikelola lewat ApprovalStepController (permission
 * approval-step.manage, admin_sistem saja) — SENGAJA tidak punya
 * destroy()/hard-delete, hanya reorder/relabel/toggle is_active, supaya
 * work_orders.approval_step_id yang masih merujuk baris ini tidak pernah
 * jadi dangling reference.
 */
class ApprovalStep extends Model
{
    protected $fillable = ['sequence_order', 'role_name', 'label', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
