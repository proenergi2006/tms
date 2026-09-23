<?php

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sparepart extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'brand', 'part_number', 'category', 'criteria', 'lead_time_months', 'unit',
        'unit_cost', 'warehouse_id', 'location', 'stock_qty', 'min_stock', 'max_stock',
        'safety_stock', 'status',
    ];

    protected $casts = [
        'stock_qty' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'safety_stock' => 'integer',
        'lead_time_months' => 'decimal:1',
        'unit_cost' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isBelowMinimumStock(): bool
    {
        return $this->stock_qty < $this->min_stock;
    }
}
