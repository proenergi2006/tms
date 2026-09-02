<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Fleet\Http\Resources\FleetResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'license_number' => $this->license_number,
            'license_expiry' => $this->license_expiry,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'fleet_id' => $this->fleet_id,
            'fleet' => new FleetResource($this->whenLoaded('fleet')),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
