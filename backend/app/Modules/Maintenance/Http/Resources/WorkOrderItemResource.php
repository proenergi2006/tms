<?php

namespace App\Modules\Maintenance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'sparepart_id' => $this->sparepart_id,
            'description' => $this->description,
            'qty' => $this->qty,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            // Foto sparepart bekas (bukti fisik pemakaian), diunggah saat
            // realisasi — lihat WorkOrderController::realizeItems().
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
