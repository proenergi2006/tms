<?php

namespace App\Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approval\Services\ApprovalWorkflowService;
use App\Modules\Fleet\Services\FleetDowntimeService;
use App\Modules\Maintenance\Http\Requests\StoreAttachmentRequest;
use App\Modules\Maintenance\Http\Requests\StoreRequestRequest;
use App\Modules\Maintenance\Http\Requests\UpdateRequestRequest;
use App\Modules\Maintenance\Http\Resources\AttachmentResource;
use App\Modules\Maintenance\Http\Resources\RequestResource;
use App\Modules\Maintenance\Models\Request as RequestModel;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\Maintenance\Services\AttachmentService;
use App\Modules\Maintenance\Services\WorkOrderItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    public function index(Request $httpRequest)
    {
        $query = RequestModel::query()
            ->with(['fleet', 'requestedBy', 'workOrder.approvalStep'])
            ->when($httpRequest->filled('status'), fn ($q) => $q->where('status', $httpRequest->query('status')))
            ->when($httpRequest->filled('fleet_id'), fn ($q) => $q->where('fleet_id', $httpRequest->query('fleet_id')))
            ->when($httpRequest->filled('type'), fn ($q) => $q->where('type', $httpRequest->query('type')))
            ->when($httpRequest->filled('priority'), fn ($q) => $q->where('priority', $httpRequest->query('priority')))
            ->when($httpRequest->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $httpRequest->query('date_from')))
            ->when($httpRequest->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $httpRequest->query('date_to')));

        // Role bercabang hanya melihat pengajuan cabangnya sendiri — cabang
        // ditentukan dari armada terkait, atau dari cabang pengaju bila
        // pengajuan tidak terikat armada spesifik (mis. restock gudang).
        if ($httpRequest->user()->isBranchScoped()) {
            $branchId = $httpRequest->user()->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('fleet', fn ($f) => $f->where('branch_id', $branchId))
                    ->orWhere(function ($q2) use ($branchId) {
                        $q2->whereNull('fleet_id')->whereHas('requestedBy', fn ($r) => $r->where('branch_id', $branchId));
                    });
            });
        }

        $requests = $query->latest()->paginate($httpRequest->integer('per_page', 15));

        return RequestResource::collection($requests);
    }

    public function mine(Request $httpRequest)
    {
        $requests = RequestModel::query()
            ->with(['fleet', 'workOrder.approvalStep'])
            ->where('requested_by', $httpRequest->user()->id)
            ->latest()
            ->paginate($httpRequest->integer('per_page', 15));

        return RequestResource::collection($requests);
    }

    /**
     * SA membuat pengajuan SEKALIGUS Work Order dalam satu langkah:
     * diagnosis, prioritas, jenis preventive/corrective, No. TAR, item
     * sparepart/biaya AWAL (murni plan/estimasi — lihat
     * WorkOrderItemService::planItem(), stok baru berkurang belakangan saat
     * realisasi lewat WorkOrderController::realizeItems()), dan — khusus
     * type=perbaikan — estimasi biaya perbaikan plus pelaksana
     * internal/eksternal (mekanik/vendor) langsung ditetapkan di sini juga
     * (lihat StoreRequestRequest). Tahap approval AWAL ditetapkan dinamis
     * lewat ApprovalWorkflowService::initializeApproval() (approval_steps
     * pertama yang aktif — lihat tabel approval_steps).
     */
    public function store(
        StoreRequestRequest $storeRequest,
        ApprovalWorkflowService $workflowService,
        FleetDowntimeService $downtimeService,
        WorkOrderItemService $itemService
    ) {
        [$requestModel, $workOrder] = DB::transaction(function () use ($storeRequest, $itemService, $workflowService) {
            $validated = $storeRequest->validated();
            $items = $validated['items'] ?? [];
            $executionType = $validated['execution_type'] ?? null;
            $mechanicId = $validated['mechanic_id'] ?? null;
            $vendorId = $validated['vendor_id'] ?? null;
            unset($validated['items'], $validated['execution_type'], $validated['mechanic_id'], $validated['vendor_id']);

            $requestModel = RequestModel::create([
                ...$validated,
                'request_no' => RequestModel::generateRequestNo(),
                // No. TAR dibuat sistem (bukan input SA) — hanya untuk
                // pengajuan perbaikan, lihat Request::generateTarNo().
                'tar_no' => $validated['type'] === 'perbaikan' ? RequestModel::generateTarNo() : null,
                'requested_by' => $storeRequest->user()->id,
                'status' => 'submitted',
            ]);

            // Work Order pendamping dibuat otomatis sejak awal (bukan baru
            // saat approval selesai) karena approval_logs mensyaratkan
            // work_order_id — approval berjalan di atas
            // WorkOrder.approval_status. execution_type/mechanic_id/
            // vendor_id sudah bisa langsung diisi di sini untuk
            // type=perbaikan (endpoint assign terpisah, WorkOrderController::
            // store(), tetap tersedia untuk jenis lain / penetapan ulang
            // belakangan).
            $workOrder = WorkOrder::create([
                'wo_no' => WorkOrder::generateWoNo(),
                'request_id' => $requestModel->id,
                'execution_type' => $executionType,
                'mechanic_id' => $mechanicId,
                'vendor_id' => $vendorId,
                'status' => 'waiting',
                'approval_status' => 'submitted',
            ]);

            $workflowService->initializeApproval($workOrder);

            // Item sparepart/biaya awal — hanya PLAN/ESTIMASI, TIDAK
            // memotong stok gudang (lihat WorkOrderItemService::planItem()
            // vs addItem()).
            foreach ($items as $itemData) {
                $itemService->planItem($workOrder, $itemData);
            }

            return [$requestModel, $workOrder];
        });

        // Pengajuan PERBAIKAN untuk armada tertentu berarti unit dianggap
        // mulai tidak beroperasi sejak dilaporkan — lihat FleetDowntimeService.
        // Jenis lain (sparepart/restock/pembelian) tidak menandakan armada
        // rusak, jadi tidak membuka window downtime.
        if ($requestModel->type === 'perbaikan' && $requestModel->fleet_id) {
            $downtimeService->open($requestModel->fleet, $workOrder);
        }

        // Notifikasi approval tertunda (FR-11) — ke role tahap approval
        // pertama yang aktif (lihat ApprovalWorkflowService::
        // initializeApproval()/notifyCurrentStage()).
        $workflowService->notifyCurrentStage($workOrder);

        return (new RequestResource($requestModel->load(['fleet', 'workOrder'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $httpRequest, RequestModel $request)
    {
        $request->loadMissing(['fleet', 'requestedBy', 'workOrder.items.sparepart', 'workOrder.approvalStep', 'workOrder.approvalLogs', 'attachments']);

        $branchId = $request->fleet?->branch_id ?? $request->requestedBy?->branch_id;
        if (! $httpRequest->user()->canAccessBranch($branchId)) {
            abort(403, 'Anda hanya dapat melihat pengajuan cabang Anda sendiri.');
        }

        return new RequestResource($request);
    }

    /**
     * Fleet Operations mengedit pengajuan SA SELAMA giliran tahap
     * approval-nya (lihat PRD: "Fleet Operations bisa mengedit pengajuan").
     * Otorisasi berlapis: permission request.edit (lihat route middleware
     * & RolePermissionSeeder), Work Order pendamping masih 'submitted', tahap
     * approval BERJALANNYA memang role caller (bukan sekadar caller punya
     * role yang benar tapi WO-nya sudah di tahap lain), dan caller punya
     * akses ke cabang pengajuan ini. execution_type/mechanic_id/vendor_id
     * disimpan di WorkOrder (bukan Request), jadi ikut diupdate di sana.
     */
    public function update(
        UpdateRequestRequest $updateRequest,
        RequestModel $request,
        ApprovalWorkflowService $workflowService,
        WorkOrderItemService $itemService
    ) {
        $request->loadMissing(['fleet', 'requestedBy', 'workOrder']);
        $workOrder = $request->workOrder;

        $this->authorizeFoTurn($request, $workOrder, $workflowService, $updateRequest->user());

        $data = $updateRequest->validated();
        $items = $data['items'] ?? null;
        unset($data['items']);
        $workOrderFields = array_intersect_key($data, array_flip(['execution_type', 'mechanic_id', 'vendor_id']));
        $requestFields = array_diff_key($data, $workOrderFields);

        DB::transaction(function () use ($request, $workOrder, $requestFields, $workOrderFields, $items, $itemService) {
            $request->update($requestFields);

            if ($workOrderFields !== []) {
                $workOrder->update($workOrderFields);
            }

            // FO mengedit rincian item sama lengkapnya dengan SA saat
            // pengajuan dibuat — item lama diganti seluruhnya dengan yang
            // baru (masih murni PLAN, tidak memotong stok, lihat
            // WorkOrderItemService::planItem() vs addItem()).
            if ($items !== null) {
                $workOrder->items()->delete();
                foreach ($items as $itemData) {
                    $itemService->planItem($workOrder, $itemData);
                }
            }
        });

        return new RequestResource($request->fresh(['fleet', 'requestedBy', 'workOrder.items.sparepart', 'workOrder.approvalStep', 'attachments']));
    }

    /**
     * SA mengajukan ulang pengajuannya sendiri yang DITOLAK — bukan
     * pengajuan baru (No. Pengajuan & No. TAR tetap sama, riwayat
     * approval_logs lama TIDAK dihapus, cuma tidak lagi jadi tahap
     * "berjalan"), tapi WorkOrder yang sama dipakai ulang: rincian boleh
     * diperbaiki (payload sama lengkapnya dengan StoreRequestRequest,
     * SA biasanya memperbaiki apa yang jadi alasan penolakan), item
     * rencana diganti seluruhnya, dan rantai approval dimulai ulang dari
     * tahap pertama lewat ApprovalWorkflowService::initializeApproval() —
     * sama seperti pengajuan baru.
     */
    public function resubmit(
        StoreRequestRequest $storeRequest,
        RequestModel $request,
        ApprovalWorkflowService $workflowService,
        FleetDowntimeService $downtimeService,
        WorkOrderItemService $itemService
    ) {
        $request->loadMissing(['fleet', 'requestedBy', 'workOrder']);
        $workOrder = $request->workOrder;
        $user = $storeRequest->user();

        if ($request->requested_by !== $user->id) {
            abort(403, 'Anda hanya dapat mengajukan ulang pengajuan Anda sendiri.');
        }

        if ($request->status !== 'rejected' || ! $workOrder) {
            abort(422, 'Hanya pengajuan yang ditolak yang dapat diajukan ulang.');
        }

        $branchId = $request->fleet?->branch_id ?? $request->requestedBy?->branch_id;
        if (! $user->canAccessBranch($branchId)) {
            abort(403, 'Anda hanya dapat mengelola pengajuan cabang Anda sendiri.');
        }

        DB::transaction(function () use ($storeRequest, $request, $workOrder, $itemService, $workflowService) {
            $validated = $storeRequest->validated();
            $items = $validated['items'] ?? [];
            $executionType = $validated['execution_type'] ?? null;
            $mechanicId = $validated['mechanic_id'] ?? null;
            $vendorId = $validated['vendor_id'] ?? null;
            unset($validated['items'], $validated['execution_type'], $validated['mechanic_id'], $validated['vendor_id']);

            $request->update([...$validated, 'status' => 'submitted']);

            $workOrder->update([
                'execution_type' => $executionType,
                'mechanic_id' => $mechanicId,
                'vendor_id' => $vendorId,
                'status' => 'waiting',
                'approval_status' => 'submitted',
                'started_at' => null,
                'finished_at' => null,
                'items_realized_at' => null,
            ]);

            $workOrder->items()->delete();
            foreach ($items as $itemData) {
                $itemService->planItem($workOrder, $itemData);
            }

            $workflowService->initializeApproval($workOrder);
        });

        if ($request->type === 'perbaikan' && $request->fleet) {
            $downtimeService->reopen($request->fleet, $workOrder);
        }

        $workflowService->notifyCurrentStage($workOrder->fresh());

        return new RequestResource($request->fresh(['fleet', 'requestedBy', 'workOrder.items.sparepart', 'workOrder.approvalStep', 'attachments']));
    }

    public function storeAttachment(
        StoreAttachmentRequest $storeRequest,
        RequestModel $request,
        AttachmentService $attachmentService,
        ApprovalWorkflowService $workflowService
    ) {
        $user = $storeRequest->user();

        // SA (request.create) mengunggah lampiran untuk pengajuan cabangnya
        // sendiri kapan saja; Fleet Operations (request.edit) HANYA selama
        // giliran tahap approval-nya — sama seperti update(), lihat
        // authorizeFoTurn(). Dua aturan berbeda per role, karena itu
        // otorisasi granular di controller, bukan satu permission middleware.
        if ($user->hasPermission('request.create')) {
            $branchId = $request->fleet?->branch_id ?? $request->requestedBy?->branch_id;
            if (! $user->canAccessBranch($branchId)) {
                abort(403, 'Anda hanya dapat mengelola pengajuan cabang Anda sendiri.');
            }
        } elseif ($user->hasPermission('request.edit')) {
            $request->loadMissing(['fleet', 'requestedBy', 'workOrder']);
            $this->authorizeFoTurn($request, $request->workOrder, $workflowService, $user);
        } else {
            abort(403, 'Anda tidak memiliki izin mengunggah lampiran pengajuan ini.');
        }

        $attachment = $attachmentService->store($request, $storeRequest->file('file'), $user, $storeRequest->input('caption'));

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }

    /**
     * Otorisasi "giliran" Fleet Operations mengedit/melampirkan pengajuan —
     * dipakai bersama oleh update() & storeAttachment() supaya aturannya
     * konsisten satu sumber.
     */
    private function authorizeFoTurn(RequestModel $request, ?WorkOrder $workOrder, ApprovalWorkflowService $workflowService, $user): void
    {
        if (! $workOrder || $workOrder->approval_status !== 'submitted') {
            abort(403, 'Pengajuan ini tidak sedang dalam tahap review yang dapat diedit.');
        }

        $step = $workflowService->currentStep($workOrder);
        if (! $step || $step->role_name !== $user->role?->name) {
            abort(403, 'Bukan giliran role Anda untuk mengedit pengajuan ini.');
        }

        $branchId = $request->fleet?->branch_id ?? $request->requestedBy?->branch_id;
        if (! $user->canAccessBranch($branchId)) {
            abort(403, 'Anda hanya dapat mengedit pengajuan cabang Anda sendiri.');
        }
    }
}
