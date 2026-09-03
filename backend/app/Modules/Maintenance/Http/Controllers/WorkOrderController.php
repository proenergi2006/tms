<?php

namespace App\Modules\Maintenance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Http\Requests\AssignWorkOrderRequest;
use App\Modules\Maintenance\Http\Requests\RealizeWorkOrderItemsRequest;
use App\Modules\Maintenance\Http\Requests\StoreAttachmentRequest;
use App\Modules\Maintenance\Http\Requests\StoreWorkOrderItemRequest;
use App\Modules\Maintenance\Http\Requests\UpdateWorkOrderStatusRequest;
use App\Modules\Maintenance\Http\Resources\AttachmentResource;
use App\Modules\Maintenance\Http\Resources\WorkOrderItemResource;
use App\Modules\Maintenance\Http\Resources\WorkOrderResource;
use App\Modules\Maintenance\Models\Request as RequestModel;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\Maintenance\Services\AttachmentService;
use App\Modules\Maintenance\Services\WorkOrderCompletionService;
use App\Modules\Maintenance\Services\WorkOrderItemService;
use App\Modules\MasterData\Models\Mechanic;
use App\Modules\SyopIntegration\Services\SyopSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkOrderController extends Controller
{
    /**
     * Tahap approval yang wajib dilalui sebelum Work Order boleh mulai
     * dikerjakan (status bergerak dari 'waiting') — approval sekarang satu
     * tahap (Kepala Pool/Leader Operations), lihat
     * ApprovalWorkflowService::currentStage().
     */
    private const CLEARED_FOR_EXECUTION = ['completed'];

    public function index(Request $httpRequest)
    {
        $query = WorkOrder::query()
            ->with(['request.fleet', 'mechanic', 'vendor'])
            ->when($httpRequest->filled('status'), fn ($q) => $q->where('status', $httpRequest->query('status')))
            ->when($httpRequest->filled('approval_status'), fn ($q) => $q->where('approval_status', $httpRequest->query('approval_status')))
            ->when($httpRequest->filled('fleet_id'), fn ($q) => $q->whereHas(
                'request',
                fn ($rq) => $rq->where('fleet_id', $httpRequest->query('fleet_id'))
            ));

        if ($httpRequest->user()->isBranchScoped()) {
            $branchId = $httpRequest->user()->branch_id;
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('request.fleet', fn ($f) => $f->where('branch_id', $branchId))
                    ->orWhere(function ($q2) use ($branchId) {
                        $q2->whereDoesntHave('request.fleet')
                            ->whereHas('request.requestedBy', fn ($r) => $r->where('branch_id', $branchId));
                    });
            });
        }

        $workOrders = $query->latest()->paginate($httpRequest->integer('per_page', 15));

        return WorkOrderResource::collection($workOrders);
    }

    public function show(Request $httpRequest, WorkOrder $workOrder)
    {
        $workOrder->load([
            'request.fleet', 'request.requestedBy', 'request.attachments', 'mechanic', 'vendor',
            'items.sparepart', 'items.attachments', 'approvalLogs.approver', 'approvalStep', 'attachments',
        ]);

        if (! $httpRequest->user()->canAccessBranch($this->requestBranchId($workOrder))) {
            abort(403, 'Anda hanya dapat melihat Work Order cabang Anda sendiri.');
        }

        return new WorkOrderResource($workOrder);
    }

    /**
     * Cabang yang berkaitan dengan Work Order ini — dari armada terkait, atau
     * dari cabang pengaju bila pengajuan tidak terikat armada spesifik.
     */
    private function requestBranchId(WorkOrder $workOrder): ?int
    {
        $workOrder->loadMissing('request.fleet', 'request.requestedBy');

        return $workOrder->request->fleet?->branch_id ?? $workOrder->request->requestedBy?->branch_id;
    }

    /**
     * Menetapkan pelaksana (mekanik internal / bengkel-vendor eksternal) pada
     * Work Order yang sudah otomatis dibuat sejak pengajuan disubmit (lihat
     * RequestController::store()) — merepresentasikan aksi "menerbitkan Work
     * Order/SPK" pada PRD Bagian 5.1 & Design Document Bagian 3.3.
     *
     * Sengaja TIDAK digembok di belakang approval_status tertentu: Tim
     * Logistik boleh menetapkan/mengganti pelaksana kapan saja selama WO
     * belum final. SA sudah bisa mengisi rincian biaya awal (work_order_items)
     * sejak pengajuan dibuat (lihat RequestController::store() &
     * WorkOrderItemService) — endpoint ini murni untuk menetapkan pelaksana
     * (internal/eksternal). Hanya WO yang belum final (bukan
     * completed/rejected) yang boleh diubah.
     */
    public function store(AssignWorkOrderRequest $assignRequest)
    {
        $requestModel = RequestModel::findOrFail($assignRequest->validated('request_id'));
        $workOrder = $requestModel->workOrder;

        if (! $workOrder) {
            throw ValidationException::withMessages([
                'request_id' => 'Work Order pendamping untuk pengajuan ini tidak ditemukan.',
            ]);
        }

        if (in_array($workOrder->approval_status, ['completed', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'request_id' => 'Work Order sudah final, tidak dapat diubah pelaksananya.',
            ]);
        }

        $branchId = $this->requestBranchId($workOrder);
        if (! $assignRequest->user()->canAccessBranch($branchId)) {
            abort(403, 'Anda hanya dapat mengelola Work Order cabang Anda sendiri.');
        }

        $data = $assignRequest->validated();

        // Mekanik internal harus dari cabang yang sama dengan Work Order-nya
        // (vendor eksternal tidak punya branch_id — dianggap bisa melayani
        // cabang mana pun, sesuai keputusan Vendor tetap referensi bersama).
        if (! empty($data['mechanic_id']) && $branchId !== null) {
            $mechanicBranchId = Mechanic::find($data['mechanic_id'])?->branch_id;
            if ($mechanicBranchId !== $branchId) {
                throw ValidationException::withMessages([
                    'mechanic_id' => 'Mekanik harus dari cabang yang sama dengan Work Order ini.',
                ]);
            }
        }

        $workOrder->update([
            'execution_type' => $data['execution_type'],
            'mechanic_id' => $data['mechanic_id'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
        ]);

        return new WorkOrderResource($workOrder->fresh(['request.fleet', 'mechanic', 'vendor']));
    }

    public function updateStatus(
        UpdateWorkOrderStatusRequest $statusRequest,
        WorkOrder $workOrder,
        WorkOrderCompletionService $completionService,
        SyopSyncService $syopSyncService
    ) {
        if (! $statusRequest->user()->canAccessBranch($this->requestBranchId($workOrder))) {
            abort(403, 'Anda hanya dapat mengelola Work Order cabang Anda sendiri.');
        }

        $newStatus = $statusRequest->validated('status');

        if ($newStatus !== 'waiting' && ! in_array($workOrder->approval_status, self::CLEARED_FOR_EXECUTION, true)) {
            throw ValidationException::withMessages([
                'status' => 'Work Order belum lolos approval, belum bisa mulai dikerjakan.',
            ]);
        }

        // Stok sparepart sekarang berkurang saat REALISASI (lihat
        // realizeItems()), bukan lagi saat pengajuan dibuat — SA wajib
        // merealisasi dulu (boleh dengan array items kosong kalau memang
        // tidak ada sparepart terpakai) sebelum WO boleh ditandai selesai.
        if ($newStatus === 'finished' && ! $workOrder->items_realized_at) {
            throw ValidationException::withMessages([
                'status' => 'Realisasi sparepart harus dilakukan sebelum WO diselesaikan.',
            ]);
        }

        $attributes = ['status' => $newStatus];
        if ($newStatus === 'on_progress' && ! $workOrder->started_at) {
            $attributes['started_at'] = now();
        }
        if ($newStatus === 'finished' && ! $workOrder->finished_at) {
            $attributes['finished_at'] = now();
        }

        $workOrder->update($attributes);
        $completionService->maybeFinalize($workOrder);

        // Armada ditandai nonaktif di SYOP selagi WO dikerjakan (supaya
        // tidak ter-dispatch operasional dari sisi SYOP), aktif kembali
        // begitu selesai — best-effort, lihat SyopSyncService::
        // setFleetActiveInSyop(). Tidak berlaku untuk request tanpa fleet
        // (mis. restock gudang) — method itu no-op kalau fleet null.
        if ($newStatus === 'on_progress' || $newStatus === 'finished') {
            $workOrder->loadMissing('request.fleet');
            $syopSyncService->setFleetActiveInSyop($workOrder->request->fleet, $newStatus === 'finished');
        }

        return new WorkOrderResource($workOrder->fresh());
    }

    public function storeItem(
        StoreWorkOrderItemRequest $itemRequest,
        WorkOrder $workOrder,
        WorkOrderItemService $itemService
    ) {
        if (! $itemRequest->user()->canAccessBranch($this->requestBranchId($workOrder))) {
            abort(403, 'Anda hanya dapat mengelola Work Order cabang Anda sendiri.');
        }

        $item = $itemService->addItem($workOrder, $itemRequest->validated());

        return (new WorkOrderItemResource($item))->response()->setStatusCode(201);
    }

    /**
     * Realisasi sparepart oleh SA setelah WO selesai dikerjakan — SATU-
     * SATUNYA titik stok gudang benar-benar berkurang untuk item asal
     * pengajuan (items yang diisi SA saat pengajuan dibuat hanyalah
     * PLAN/ESTIMASI via WorkOrderItemService::planItem(), tidak berefek ke
     * stok — lihat RequestController::store()). Daftar item PLAN lama
     * dihapus lalu digantikan daftar REALISASI final (boleh kosong — berarti
     * memang tidak ada sparepart yang benar-benar terpakai), masing-masing
     * lewat WorkOrderItemService::addItem() (mengunci & mengurangi stok).
     * WorkOrderController::updateStatus() menolak transisi ke 'finished'
     * sebelum items_realized_at terisi lewat endpoint ini.
     */
    public function realizeItems(
        RealizeWorkOrderItemsRequest $realizeRequest,
        WorkOrder $workOrder,
        WorkOrderItemService $itemService,
        AttachmentService $attachmentService
    ) {
        if (! $realizeRequest->user()->canAccessBranch($this->requestBranchId($workOrder))) {
            abort(403, 'Anda hanya dapat mengelola Work Order cabang Anda sendiri.');
        }

        $items = $realizeRequest->validated('items', []);
        $diagnosis = $realizeRequest->validated('diagnosis');

        DB::transaction(function () use ($realizeRequest, $workOrder, $items, $diagnosis, $itemService, $attachmentService) {
            $workOrder->items()->delete();

            foreach ($items as $index => $itemData) {
                // 'photo' bukan kolom work_order_items — lepas dulu sebelum
                // dilempar ke addItem(), lalu simpan sebagai Attachment
                // tersendiri tertaut ke item yang baru dibuat (bukti fisik
                // sparepart bekas, wajib untuk item ber-sparepart_id — lihat
                // RealizeWorkOrderItemsRequest::withValidator()).
                unset($itemData['photo']);
                $item = $itemService->addItem($workOrder, $itemData);

                if ($realizeRequest->hasFile("items.{$index}.photo")) {
                    $attachmentService->store(
                        $item,
                        $realizeRequest->file("items.{$index}.photo"),
                        $realizeRequest->user(),
                        'Foto sparepart bekas'
                    );
                }
            }

            $workOrder->update(['items_realized_at' => now()]);

            if ($diagnosis !== null) {
                $workOrder->request->update(['diagnosis' => $diagnosis]);
            }
        });

        return new WorkOrderResource($workOrder->fresh(['items.sparepart', 'items.attachments', 'request']));
    }

    public function storeAttachment(
        StoreAttachmentRequest $storeRequest,
        WorkOrder $workOrder,
        AttachmentService $attachmentService
    ) {
        if (! $storeRequest->user()->canAccessBranch($this->requestBranchId($workOrder))) {
            abort(403, 'Anda hanya dapat mengelola Work Order cabang Anda sendiri.');
        }

        $attachment = $attachmentService->store($workOrder, $storeRequest->file('file'), $storeRequest->user(), $storeRequest->input('caption'));

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }
}
