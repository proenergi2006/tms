<?php

namespace App\Modules\Fleet\Models;

use App\Modules\MasterData\Models\CostType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalCost extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'fleet_id', 'cost_type_id', 'amount', 'source_type', 'source_id', 'incurred_at',
    ];

    protected $casts = [
        'incurred_at' => 'date',
        'amount' => 'decimal:2',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }
}
