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
        'sku', 'name', 'category', 'unit', 'unit_cost', 'warehouse_id', 'stock_qty', 'min_stock',
    ];

    protected $casts = [
        'stock_qty' => 'integer',
        'min_stock' => 'integer',
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
