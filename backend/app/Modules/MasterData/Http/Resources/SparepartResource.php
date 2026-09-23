<?php

namespace App\Modules\MasterData\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SparepartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'part_number' => $this->part_number,
            'category' => $this->category,
            'criteria' => $this->criteria,
            'lead_time_months' => $this->lead_time_months,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'location' => $this->location,
            'stock_qty' => $this->stock_qty,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'safety_stock' => $this->safety_stock,
            'status' => $this->status,
            'is_below_minimum_stock' => $this->isBelowMinimumStock(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
