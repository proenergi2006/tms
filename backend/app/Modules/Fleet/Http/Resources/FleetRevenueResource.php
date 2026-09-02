<?php

namespace App\Modules\Fleet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetRevenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fleet_id' => $this->fleet_id,
            'period' => $this->period,
            'source_po_number' => $this->source_po_number,
            'amount' => $this->amount,
            'synced_at' => $this->synced_at,
        ];
    }
}
