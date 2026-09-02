<?php

namespace App\Modules\Maintenance\Http\Resources;

use App\Modules\MasterData\Http\Resources\JobTypeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workOrder = $this->whenLoaded('workOrder');

        return [
            'id' => $this->id,
            'fleet_id' => $this->fleet_id,
            'work_order_id' => $this->work_order_id,
            'job_type_id' => $this->job_type_id,
            'job_type' => new JobTypeResource($this->whenLoaded('jobType')),
            'description' => $this->description,
            'cost' => $this->cost,
            'performed_at' => $this->performed_at,
            // "oleh siapa" — nama pelaksana (mekanik internal atau vendor
            // eksternal, lihat WorkOrder::execution_type).
            'wo_no' => $workOrder?->wo_no,
            'executor' => $workOrder?->mechanic?->name ?? $workOrder?->vendor?->name,
            // "apa yang pernah diganti" — rincian item/sparepart dari WO ini.
            'items' => $workOrder
                ? $workOrder->items->map(fn ($item) => [
                    'description' => $item->sparepart?->name ?? $item->description,
                    'qty' => $item->qty,
                    'total_cost' => $item->total_cost,
                ])->values()
                : [],
        ];
    }
}
