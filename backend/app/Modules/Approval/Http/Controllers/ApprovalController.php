<?php

namespace App\Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approval\Http\Requests\ApproveWorkOrderRequest;
use App\Modules\Approval\Http\Requests\RejectWorkOrderRequest;
use App\Modules\Approval\Http\Resources\ApprovalLogResource;
use App\Modules\Approval\Models\ApprovalLog;
use App\Modules\Approval\Services\ApprovalWorkflowService;
use App\Modules\Maintenance\Http\Resources\WorkOrderResource;
use App\Modules\Maintenance\Models\WorkOrder;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * Antrean approval sekarang sepenuhnya DINAMIS: role approver tahap
     * berjalan ditentukan lewat kolom approval_steps.role_name (bukan
     * daftar role hardcoded di kode ini lagi) — lihat tabel approval_steps
     * & ApprovalWorkflowService. Sebuah WO 'submitted' muncul di antrean
     * user hanya kalau approval_step_id-nya SEDANG menunjuk tahap dengan
     * role_name yang sama dengan role user tsb, lalu difilter cabang lewat
     * User::canAccessBranch() (semua role approval branch-scoped sekarang).
     */
    public function pending(Request $httpRequest)
    {
        $user = $httpRequest->user();
        $role = $user->role?->name;

        $workOrders = WorkOrder::with(['request.fleet', 'request.requestedBy', 'approvalStep'])
            ->where('approval_status', 'submitted')
            ->whereHas('approvalStep', fn ($q) => $q->where('role_name', $role))
            ->latest()
            ->get()
            ->filter(fn (WorkOrder $wo) => $user->canAccessBranch($this->branchId($wo)))
            ->values();

        return WorkOrderResource::collection($workOrders);
    }

    public function history(Request $httpRequest)
    {
        $logs = ApprovalLog::with(['workOrder.request.fleet'])
            ->where('approver_user_id', $httpRequest->user()->id)
            ->latest('approved_at')
            ->paginate($httpRequest->integer('per_page', 15));

        return ApprovalLogResource::collection($logs);
    }

    public function approve(ApproveWorkOrderRequest $approveRequest, WorkOrder $workOrder, ApprovalWorkflowService $workflowService)
    {
        $workOrder = $workflowService->approve($workOrder, $approveRequest->user(), $approveRequest->validated('notes'));

        return new WorkOrderResource($workOrder->load(['request.fleet', 'approvalLogs.approver']));
    }

    public function reject(RejectWorkOrderRequest $rejectRequest, WorkOrder $workOrder, ApprovalWorkflowService $workflowService)
    {
        $workOrder = $workflowService->reject($workOrder, $rejectRequest->user(), $rejectRequest->validated('reason'));

        return new WorkOrderResource($workOrder->load(['request.fleet', 'approvalLogs.approver']));
    }

    /**
     * Cabang yang berkaitan dengan Work Order ini — dari armada terkait,
     * atau dari cabang pengaju bila pengajuan tidak terikat armada
     * spesifik (mis. restock gudang).
     */
    private function branchId(WorkOrder $workOrder): ?int
    {
        $workOrder->loadMissing('request.fleet', 'request.requestedBy');

        return $workOrder->request->fleet?->branch_id ?? $workOrder->request->requestedBy?->branch_id;
    }
}
