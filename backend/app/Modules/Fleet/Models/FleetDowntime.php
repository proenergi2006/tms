<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Maintenance\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetDowntime extends Model
{
    use HasFactory;

    protected $fillable = ['fleet_id', 'work_order_id', 'started_at', 'ended_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function durationMinutes(): ?int
    {
        return $this->ended_at ? $this->started_at->diffInMinutes($this->ended_at) : null;
    }
}
