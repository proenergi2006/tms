<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['fleet_id', 'log_date', 'liters', 'cost', 'odometer', 'engine_hours'];

    protected $casts = [
        'log_date' => 'date',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
