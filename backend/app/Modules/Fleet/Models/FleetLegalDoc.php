<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetLegalDoc extends Model
{
    use HasFactory;

    protected $fillable = [
        'fleet_id', 'doc_type', 'doc_number', 'issued_date', 'expiry_date', 'file_path',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date->isFuture() && now()->diffInDays($this->expiry_date, false) <= $days;
    }
}
