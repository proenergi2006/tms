<?php

namespace App\Modules\Fleet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fleet_id' => $this->fleet_id,
            'log_date' => $this->log_date,
            'liters' => $this->liters,
            'cost' => $this->cost,
            'odometer' => $this->odometer,
            'engine_hours' => $this->engine_hours,
        ];
    }
}
