<?php

namespace App\Modules\Maintenance\Http\Resources;

use App\Modules\Fleet\Http\Resources\FleetResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_no' => $this->request_no,
            'type' => $this->type,
            'maintenance_nature' => $this->maintenance_nature,
            'priority' => $this->priority,
            'fleet_id' => $this->fleet_id,
            'fleet' => new FleetResource($this->whenLoaded('fleet')),
            'tar_no' => $this->tar_no,
            'diagnosis' => $this->diagnosis,
            'estimated_days' => $this->estimated_days,
            'odometer_km' => $this->odometer_km,
            // $this->trouble_date sudah di-cast model ke Carbon ('date:Y-m-d'),
            // tapi format cast itu cuma dipakai Model::toArray() — di dalam
            // Resource, Carbon di-JSON-encode lewat jsonSerialize() bawaannya
            // sendiri (ISO 8601 + jam), bukan format cast. Tanpa ->format()
            // eksplisit di sini, <input type="date"> di frontend menerima
            // string yang tidak dikenalinya dan tampil kosong walau nilainya
            // sudah terisi (lihat RequestForm.vue).
            'trouble_date' => $this->trouble_date?->format('Y-m-d'),
            'suggestion' => $this->suggestion,
            'action_taken' => $this->action_taken,
            'requested_by' => $this->requested_by,
            'requested_by_name' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy->name),
            'description' => $this->description,
            'status' => $this->status,
            'work_order_id' => $this->whenLoaded('workOrder', fn () => $this->workOrder?->id),
            // Tahap approval yang SEDANG menunggu tindakan (role_name +
            // label manusiawi dari approval_steps, mis. "Verifikasi Fleet
            // Operations") — supaya siapa pun yang melihat status
            // "submitted" langsung tahu bola ada di tangan role apa,
            // tanpa harus buka detail Work Order. null begitu approval
            // sudah selesai/ditolak (approval_step_id ikut null-kan di
            // ApprovalWorkflowService saat itu terjadi).
            'waiting_approval_role' => $this->whenLoaded(
                'workOrder',
                fn () => $this->status === 'submitted' ? $this->workOrder?->approvalStep?->role_name : null
            ),
            'waiting_approval_label' => $this->whenLoaded(
                'workOrder',
                fn () => $this->status === 'submitted' ? $this->workOrder?->approvalStep?->label : null
            ),
            // Alasan penolakan tahap terakhir — dipakai frontend supaya SA
            // langsung lihat apa yang perlu diperbaiki saat mengajukan
            // ulang (RequestForm.vue mode resubmit), tanpa harus buka
            // Detail Work Order dulu untuk membaca Timeline Approval.
            // whenLoaded() TIDAK mendukung dot-notation untuk relasi
            // bersarang (cuma cek relationLoaded() langsung di $this->
            // resource) — makanya dicek manual dua lapis di dalam closure.
            'rejection_reason' => $this->whenLoaded('workOrder', function () {
                if ($this->status !== 'rejected' || ! $this->workOrder?->relationLoaded('approvalLogs')) {
                    return null;
                }

                return $this->workOrder->approvalLogs->where('action', 'reject')->last()?->notes;
            }),
            // execution_type/mechanic_id/vendor_id disimpan di WorkOrder
            // (bukan Request) sejak SA mengisinya lewat RequestController::
            // store() — tanpa field ini di sini, form edit Fleet Operations
            // selalu menampilkannya kosong walau SA sudah mengisinya (lihat
            // RequestForm.vue, populate mode-edit).
            'execution_type' => $this->whenLoaded('workOrder', fn () => $this->workOrder?->execution_type),
            'mechanic_id' => $this->whenLoaded('workOrder', fn () => $this->workOrder?->mechanic_id),
            'vendor_id' => $this->whenLoaded('workOrder', fn () => $this->workOrder?->vendor_id),
            // Rincian item WO — diekspos di sini (bukan cuma work_order_id)
            // supaya FO bisa melihat & mengedit rincian yang sama lengkapnya
            // dengan yang diisi SA saat membuat pengajuan (lihat
            // RequestController::update()).
            'items' => $this->whenLoaded('workOrder', fn () => WorkOrderItemResource::collection($this->workOrder?->items ?? [])),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
