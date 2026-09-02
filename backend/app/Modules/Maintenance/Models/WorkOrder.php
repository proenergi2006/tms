<?php

namespace App\Modules\Maintenance\Models;

use App\Modules\Approval\Models\ApprovalLog;
use App\Modules\Approval\Models\ApprovalStep;
use App\Modules\MasterData\Models\Mechanic;
use App\Modules\MasterData\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkOrder extends Model
{
    use HasFactory;

    protected $table = 'work_orders';

    protected $fillable = [
        'wo_no', 'request_id', 'execution_type', 'mechanic_id', 'vendor_id',
        'status', 'approval_status', 'approval_step_id', 'started_at', 'finished_at',
        'items_realized_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'items_realized_at' => 'datetime',
    ];

    public function approvalStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Generator nomor WO sederhana, bukan solusi aman untuk konkurensi tinggi
     * — pemanggil membungkusnya dalam transaksi DB.
     */
    public static function generateWoNo(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('WO-%d-%05d', $year, $count);
    }

    public function totalCost(): float
    {
        return (float) $this->items()->sum('total_cost');
    }
}
