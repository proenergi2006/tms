<?php

namespace App\Modules\Maintenance\Models;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\MasterData\Models\JobType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceHistory extends Model
{
    use HasFactory;

    protected $table = 'maintenance_history';

    const UPDATED_AT = null;

    protected $fillable = [
        'fleet_id', 'work_order_id', 'job_type_id', 'description', 'cost', 'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'cost' => 'decimal:2',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function jobType(): BelongsTo
    {
        return $this->belongsTo(JobType::class);
    }
}
