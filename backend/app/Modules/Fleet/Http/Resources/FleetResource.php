<?php

namespace App\Modules\Fleet\Http\Resources;

use App\Modules\MasterData\Http\Resources\BranchResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plate_number' => $this->plate_number,
            'fleet_type' => $this->fleet_type,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'chassis_number' => $this->chassis_number,
            'engine_number' => $this->engine_number,
            'keur_number' => $this->keur_number,
            'capacity' => $this->capacity,
            'purchase_price' => $this->purchase_price,
            'ownership' => $this->ownership,
            'leasing_status' => $this->leasing_status,
            'b3_dishub_number' => $this->b3_dishub_number,
            'mutation_status' => $this->mutation_status,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'status' => $this->status,
            'last_inspection_at' => $this->last_inspection_at,
            'inspection_due' => $this->isInspectionDue(),
            'inspection_due_date' => $this->inspectionDueDate(),
            'service_interval_km' => $this->service_interval_km,
            'service_interval_engine_hours' => $this->service_interval_engine_hours,
            'service_interval_months' => $this->service_interval_months,
            'last_service_at' => $this->last_service_at,
            'last_service_odometer' => $this->last_service_odometer,
            'last_service_engine_hours' => $this->last_service_engine_hours,
            'current_odometer' => $this->currentOdometer(),
            'current_engine_hours' => $this->currentEngineHours(),
            'service_due' => $this->isServiceDue(),
            'service_due_reasons' => $this->serviceDueReasons(),
            'current_downtime_since' => $this->currentDowntime()?->started_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
