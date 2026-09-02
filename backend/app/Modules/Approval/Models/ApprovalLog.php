<?php

namespace App\Modules\Approval\Models;

use App\Models\User;
use App\Modules\Maintenance\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'approver_role', 'approver_user_id', 'action', 'notes', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
