<?php

namespace App\Modules\Fleet\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Manajemen ban & komponen major lain (aki, oli, rem) — permintaan fitur
 * eksplisit. Beda dari Fleet::serviceDueReasons() (satu jadwal servis umum
 * per armada): di sini tiap KOMPONEN punya baseline & interval sendiri,
 * karena umur pakainya jauh berbeda (ban ~40.000km, oli ~5.000km, dst).
 */
class FleetComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'fleet_id', 'component_type', 'last_replaced_at', 'last_replaced_odometer',
        'interval_km', 'interval_months', 'notes',
    ];

    protected $casts = [
        'last_replaced_at' => 'date',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    /**
     * Alasan komponen ini sudah jatuh tempo diganti — sama pola dengan
     * Fleet::serviceDueReasons(): dimensi km butuh baseline odometer DAN
     * pembacaan terkini, dimensi waktu fallback ke created_at bila belum
     * pernah diganti sama sekali.
     */
    public function dueReasons(): array
    {
        $reasons = [];
        $currentOdometer = $this->fleet?->currentOdometer();

        if ($this->interval_km !== null && $this->last_replaced_odometer !== null && $currentOdometer !== null) {
            if ($currentOdometer - $this->last_replaced_odometer >= $this->interval_km) {
                $reasons[] = 'km';
            }
        }

        if ($this->interval_months !== null) {
            $baseline = $this->last_replaced_at ?? $this->created_at;
            if ($baseline instanceof Carbon && $baseline->copy()->addMonths($this->interval_months)->isPast()) {
                $reasons[] = 'time';
            }
        }

        return $reasons;
    }

    public function isDue(): bool
    {
        return count($this->dueReasons()) > 0;
    }
}
