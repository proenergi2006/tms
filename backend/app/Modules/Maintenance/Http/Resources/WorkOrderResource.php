<?php

namespace App\Modules\Maintenance\Http\Resources;

use App\Modules\Approval\Http\Resources\ApprovalLogResource;
use App\Modules\Approval\Http\Resources\ApprovalStepResource;
use App\Modules\MasterData\Http\Resources\MechanicResource;
use App\Modules\MasterData\Http\Resources\VendorResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wo_no' => $this->wo_no,
            'request_id' => $this->request_id,
            'request' => new RequestResource($this->whenLoaded('request')),
            'execution_type' => $this->execution_type,
            'mechanic_id' => $this->mechanic_id,
            'mechanic' => new MechanicResource($this->whenLoaded('mechanic')),
            'vendor_id' => $this->vendor_id,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'status' => $this->status,
            'approval_status' => $this->approval_status,
            'approval_step_id' => $this->approval_step_id,
            'approval_step' => new ApprovalStepResource($this->whenLoaded('approvalStep')),
            'total_cost' => $this->totalCost(),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'items_realized_at' => $this->items_realized_at,
            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),
            'approval_logs' => ApprovalLogResource::collection($this->whenLoaded('approvalLogs')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
