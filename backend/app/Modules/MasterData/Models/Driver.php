<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Fleet\Models\Fleet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'license_number', 'license_expiry', 'phone',
        'branch_id', 'fleet_id', 'status', 'syop_driver_id',
    ];

    protected $casts = [
        'license_expiry' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
