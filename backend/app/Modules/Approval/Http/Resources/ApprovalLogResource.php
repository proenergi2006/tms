<?php

namespace App\Modules\Approval\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'approver_role' => $this->approver_role,
            'approver_user_id' => $this->approver_user_id,
            'approver_name' => $this->whenLoaded('approver', fn () => $this->approver->name),
            'action' => $this->action,
            'notes' => $this->notes,
            'approved_at' => $this->approved_at,
            'work_order' => $this->whenLoaded('workOrder', fn () => [
                'id' => $this->workOrder->id,
                'wo_no' => $this->workOrder->wo_no,
                'fleet' => $this->workOrder->request?->fleet?->only(['id', 'plate_number']),
            ]),
        ];
    }
}
