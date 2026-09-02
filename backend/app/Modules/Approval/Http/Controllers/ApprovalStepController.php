<?php

namespace App\Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approval\Http\Requests\ApprovalStepRequest;
use App\Modules\Approval\Http\Resources\ApprovalStepResource;
use App\Modules\Approval\Models\ApprovalStep;

/**
 * Manajemen tahap approval — Approval Workflow Engine dinamis (PRD: "approval
 * seharusnya tidak hardcode, harus dinamis/bisa dikonfigurasi"). Permission
 * `approval-step.manage`, khusus admin_sistem (lihat RolePermissionSeeder).
 *
 * SENGAJA TIDAK ADA destroy() — tahap hanya boleh direorder/relabel/
 * dinonaktifkan (is_active=false), tidak pernah dihapus permanen, supaya
 * work_orders.approval_step_id yang masih merujuk baris lama tidak pernah
 * jadi dangling reference (lihat migrasi
 * add_approval_step_id_to_work_orders_table, nullOnDelete cuma jaga-jaga).
 */
class ApprovalStepController extends Controller
{
    public function index()
    {
        $steps = ApprovalStep::orderBy('sequence_order')->get();

        return ApprovalStepResource::collection($steps);
    }

    public function store(ApprovalStepRequest $storeRequest)
    {
        $step = ApprovalStep::create($storeRequest->validated());

        return (new ApprovalStepResource($step))->response()->setStatusCode(201);
    }

    public function update(ApprovalStepRequest $updateRequest, ApprovalStep $approvalStep)
    {
        $approvalStep->update($updateRequest->validated());

        return new ApprovalStepResource($approvalStep);
    }
}
