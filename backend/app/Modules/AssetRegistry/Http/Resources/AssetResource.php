<?php

namespace App\Modules\AssetRegistry\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_code' => $this->asset_code,
            'category' => $this->category,
            'name' => $this->name,
            'pic' => $this->pic,
            'pic_name' => $this->whenLoaded('picUser', fn () => $this->picUser?->name),
            'location' => $this->location,
            'purchase_date' => $this->purchase_date,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
