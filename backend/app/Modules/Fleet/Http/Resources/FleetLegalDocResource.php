<?php

namespace App\Modules\Fleet\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FleetLegalDocResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fleet_id' => $this->fleet_id,
            'doc_type' => $this->doc_type,
            'doc_number' => $this->doc_number,
            'issued_date' => $this->issued_date,
            'expiry_date' => $this->expiry_date,
            'file_path' => $this->file_path,
            'is_expiring_soon' => $this->isExpiringSoon(),
        ];
    }
}
