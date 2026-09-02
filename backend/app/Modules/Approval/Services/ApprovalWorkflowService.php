<?php

namespace App\Modules\Approval\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Approval\Models\ApprovalLog;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\Fleet\Services\FleetDowntimeService;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\Maintenance\Services\WorkOrderCompletionService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval Workflow Engine — DINAMIS (data-driven lewat tabel
 * approval_steps, dikelola via ApprovalStepController), bukan lagi
 * kondisional role hardcoded di PHP. work_orders.approval_status hanya
 * punya ['submitted', 'completed', 'rejected']; work_orders.approval_step_id
 * menunjuk baris approval_steps yang SEDANG menunggu (null berarti WO sudah
 * completed/rejected).
 *
 * Setiap cabang sekarang punya SA, Fleet Operations, DAN Kepala Pool
 * sendiri, jadi SETIAP role approval (apa pun urutannya) bersifat
 * branch-scoped secara seragam — tidak ada lagi kekhususan
 * kepala_pool-vs-region seperti sebelumnya. Otorisasi approve/reject cukup
 * mencocokkan role approver dengan approval_steps.role_name pada tahap
 * berjalan, DAN canAccessBranch() ke cabang Work Order tsb.
 */
class ApprovalWorkflowService
{
    public function __construct(
        private readonly WorkOrderCompletionService $completionService,
        private readonly NotificationService $notificationService,
        private readonly FleetDowntimeService $downtimeService,
    ) {}

    /**
     * Menetapkan tahap approval AWAL untuk Work Order yang baru dibuat —
     * dipanggil dari RequestController::store() dalam transaksi yang sama
     * dengan pembuatan WorkOrder. Kasus tepi: kalau tidak ada satu pun tahap
     * approval aktif (seluruh approval_steps di-nonaktifkan admin_sistem),
     * WO langsung dianggap completed tanpa approval manual sama sekali.
     */
    public function initializeApproval(WorkOrder $workOrder): void
    {
        $firstStep = ApprovalStep::where('is_active', true)->orderBy('sequence_order')->first();

        if (! $firstStep) {
            $workOrder->update(['approval_status' => 'completed', 'approval_step_id' => null]);
            $workOrder->request()->update(['status' => 'completed']);

            return;
        }

        $workOrder->update(['approval_step_id' => $firstStep->id]);
    }

    public function approve(WorkOrder $workOrder, User $approver, ?string $notes = null): WorkOrder
    {
        $step = $this->currentStep($workOrder);

        if (! $step) {
            throw ValidationException::withMessages([
                'approval_status' => 'Work Order tidak sedang menunggu approval manual.',
            ]);
        }

        $this->authorizeStep($approver, $step, $this->requestBranchId($workOrder));

        $nextStep = ApprovalStep::where('is_active', true)
            ->where('sequence_order', '>', $step->sequence_order)
            ->orderBy('sequence_order')
            ->first();

        DB::transaction(function () use ($workOrder, $approver, $notes, $step, $nextStep) {
            $isFinal = ! $nextStep;
            $nextStatus = $isFinal ? 'completed' : 'submitted';

            $this->log($workOrder, $approver, $step->role_name, 'approve', $notes, $nextStatus);

            $workOrder->update([
                'approval_step_id' => $nextStep?->id,
                'approval_status' => $nextStatus,
            ]);

            if ($isFinal) {
                $workOrder->request()->update(['status' => 'completed']);
            }
        });

        $this->completionService->maybeFinalize($workOrder);
        $this->notifyCurrentStage($workOrder);

        return $workOrder->fresh();
    }

    public function reject(WorkOrder $workOrder, User $approver, string $reason): WorkOrder
    {
        $step = $this->currentStep($workOrder);

        if (! $step) {
            throw ValidationException::withMessages([
                'approval_status' => 'Work Order tidak sedang menunggu approval manual.',
            ]);
        }

        $this->authorizeStep($approver, $step, $this->requestBranchId($workOrder));

        DB::transaction(function () use ($workOrder, $approver, $reason, $step) {
            $this->log($workOrder, $approver, $step->role_name, 'reject', $reason, 'rejected');
            $workOrder->update(['approval_status' => 'rejected', 'approval_step_id' => null]);
            $workOrder->request()->update(['status' => 'rejected']);
        });

        // Perbaikan ditolak berarti tidak jadi dikerjakan — tutup window
        // downtime-nya (kalau ada) supaya armada tidak "tersangkut" status
        // maintenance selamanya (lihat FleetDowntimeService).
        if ($workOrder->request->fleet) {
            $this->downtimeService->close($workOrder->request->fleet, $workOrder);
        }

        $this->notifyRejection($workOrder, $reason);

        return $workOrder->fresh();
    }

    /**
     * Tahap approval yang SEDANG menunggu untuk Work Order ini, atau null
     * bila tidak sedang menunggu approval manual apa pun (approval_status
     * bukan 'submitted', atau approval_step_id kosong — mis. kasus tepi
     * "nol tahap aktif" pada initializeApproval()).
     */
    public function currentStep(WorkOrder $workOrder): ?ApprovalStep
    {
        if ($workOrder->approval_status !== 'submitted' || ! $workOrder->approval_step_id) {
            return null;
        }

        return $workOrder->relationLoaded('approvalStep')
            ? $workOrder->approvalStep
            : ApprovalStep::find($workOrder->approval_step_id);
    }

    private function authorizeStep(User $approver, ApprovalStep $step, ?int $branchId): void
    {
        if ($approver->role?->name !== $step->role_name) {
            abort(403, 'Role Anda tidak berwenang melakukan approval pada tahap ini.');
        }

        if (! $approver->canAccessBranch($branchId)) {
            abort(403, 'Anda hanya dapat melakukan approval untuk cabang Anda sendiri.');
        }
    }

    /**
     * Cabang yang berkaitan dengan Work Order ini — dari armada terkait, atau
     * dari cabang pengaju bila pengajuan tidak terikat armada spesifik
     * (mis. restock gudang). Dipakai untuk membatasi approval per cabang
     * lewat User::canAccessBranch() — sekarang berlaku SERAGAM untuk semua
     * role approval (fleet_operations, kepala_pool, dst) karena semuanya
     * branch-scoped.
     */
    private function requestBranchId(WorkOrder $workOrder): ?int
    {
        $workOrder->loadMissing('request.fleet', 'request.requestedBy');

        return $workOrder->request->fleet?->branch_id ?? $workOrder->request->requestedBy?->branch_id;
    }

    private function log(WorkOrder $workOrder, User $approver, string $role, string $action, ?string $notes, string $nextStatus): void
    {
        ApprovalLog::create([
            'work_order_id' => $workOrder->id,
            'approver_role' => $role,
            'approver_user_id' => $approver->id,
            'action' => $action,
            'notes' => $notes,
            'approved_at' => now(),
        ]);

        AuditLog::create([
            'actor_id' => $approver->id,
            'action' => "work_order.{$action}",
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'before_data' => ['approval_status' => $workOrder->getOriginal('approval_status')],
            'after_data' => ['approval_status' => $nextStatus],
        ]);
    }

    /**
     * Notifikasi approval tertunda (FR-11). Dipanggil setelah setiap approve()
     * maupun langsung setelah Work Order dibuat (RequestController::store(),
     * notifikasi awal ke approver tahap pertama).
     */
    public function notifyCurrentStage(WorkOrder $workOrder): void
    {
        $workOrder->loadMissing('request.fleet', 'request.requestedBy');

        if ($workOrder->approval_status === 'completed') {
            if ($workOrder->request->requestedBy) {
                $this->notificationService->notifyUser(
                    $workOrder->request->requestedBy,
                    'request_completed',
                    "Pengajuan {$workOrder->request->request_no} Anda telah selesai."
                );
            }

            return;
        }

        $step = $this->currentStep($workOrder);
        if (! $step) {
            return;
        }

        $fleetLabel = $workOrder->request->fleet?->plate_number ?? 'tanpa armada spesifik';
        $message = "Work Order {$workOrder->wo_no} ({$fleetLabel}) menunggu approval Anda.";

        $this->notificationService->notifyRole($step->role_name, 'approval_pending', $message, $this->requestBranchId($workOrder));
    }

    private function notifyRejection(WorkOrder $workOrder, string $reason): void
    {
        $workOrder->loadMissing('request.requestedBy');

        if ($workOrder->request->requestedBy) {
            $this->notificationService->notifyUser(
                $workOrder->request->requestedBy,
                'request_rejected',
                "Pengajuan {$workOrder->request->request_no} Anda ditolak. Alasan: {$reason}"
            );
        }
    }
}
