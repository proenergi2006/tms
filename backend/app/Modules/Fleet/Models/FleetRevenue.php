<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetRevenue extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'fleet_id', 'period', 'source_po_number', 'amount', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
