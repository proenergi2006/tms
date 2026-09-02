<?php

namespace App\Modules\Fleet\Http\Resources;

use App\Modules\MasterData\Http\Resources\CostTypeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fleet_id' => $this->fleet_id,
            'cost_type_id' => $this->cost_type_id,
            'cost_type' => new CostTypeResource($this->whenLoaded('costType')),
            'amount' => $this->amount,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'incurred_at' => $this->incurred_at,
        ];
    }
}
