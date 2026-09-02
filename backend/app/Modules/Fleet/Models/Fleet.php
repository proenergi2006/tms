<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Maintenance\Models\MaintenanceHistory;
use App\Modules\Maintenance\Models\Request as MaintenanceRequest;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\Driver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Fleet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'syop_fleet_id',
        'plate_number', 'fleet_type', 'brand', 'model', 'year',
        'chassis_number', 'engine_number', 'keur_number',
        'capacity', 'purchase_price', 'ownership', 'leasing_status', 'b3_dishub_number', 'mutation_status',
        'branch_id', 'status', 'last_inspection_at',
        'service_interval_km', 'service_interval_engine_hours', 'service_interval_months',
        'last_service_at', 'last_service_odometer', 'last_service_engine_hours',
    ];

    protected $casts = [
        'last_inspection_at' => 'datetime',
        'last_service_at' => 'date',
    ];

    /**
     * Setiap armada wajib masuk workshop untuk dicek mekanik tiap 2 minggu,
     * baik rusak atau tidak. Dasar perhitungan: last_inspection_at (diisi
     * otomatis saat Work Order manapun untuk armada ini selesai — lihat
     * WorkOrderCompletionService::maybeFinalize()), fallback ke created_at
     * untuk armada yang belum pernah masuk workshop sama sekali.
     */
    public const INSPECTION_INTERVAL_DAYS = 14;

    public function inspectionDueDate(): Carbon
    {
        return ($this->last_inspection_at ?? $this->created_at)->copy()->addDays(self::INSPECTION_INTERVAL_DAYS);
    }

    public function isInspectionDue(): bool
    {
        return $this->inspectionDueDate()->isPast();
    }

    /**
     * Servis berkala (oli, filter, dsb — bukan cek 2 mingguan di atas)
     * dijadwalkan berdasar KM, jam operasi mesin, atau waktu — whichever
     * comes first, praktik standar preventive maintenance armada (lihat
     * permintaan fitur di memori project). Ambang tiap dimensi dikonfigurasi
     * per armada (service_interval_*, nullable — dimensi yang tidak diisi
     * dilewati). Baseline (last_service_*) diisi otomatis saat Work Order
     * manapun untuk armada ini selesai (WorkOrderCompletionService), sama
     * seperti last_inspection_at.
     */
    public function currentOdometer(): ?int
    {
        return $this->fuelLogs()->whereNotNull('odometer')->orderByDesc('log_date')->orderByDesc('id')->value('odometer');
    }

    public function currentEngineHours(): ?int
    {
        return $this->fuelLogs()->whereNotNull('engine_hours')->orderByDesc('log_date')->orderByDesc('id')->value('engine_hours');
    }

    /**
     * Dimensi mana saja yang sudah melewati ambang sejak servis terakhir.
     * Dimensi km/jam-operasi butuh baseline (last_service_odometer/
     * last_service_engine_hours) DAN pembacaan terkini untuk bisa dievaluasi
     * — tanpa itu dilewati (bukan dianggap jatuh tempo), sama seperti
     * cost-per-km yang butuh data odometer memadai sebelum menghitung.
     * Dimensi waktu selalu bisa dievaluasi (fallback baseline ke created_at).
     */
    public function serviceDueReasons(): array
    {
        $reasons = [];

        if ($this->service_interval_km !== null && $this->last_service_odometer !== null) {
            $current = $this->currentOdometer();
            if ($current !== null && $current - $this->last_service_odometer >= $this->service_interval_km) {
                $reasons[] = 'km';
            }
        }

        if ($this->service_interval_engine_hours !== null && $this->last_service_engine_hours !== null) {
            $current = $this->currentEngineHours();
            if ($current !== null && $current - $this->last_service_engine_hours >= $this->service_interval_engine_hours) {
                $reasons[] = 'engine_hours';
            }
        }

        if ($this->service_interval_months !== null) {
            $baseline = $this->last_service_at ?? $this->created_at;
            if ($baseline->copy()->addMonths($this->service_interval_months)->isPast()) {
                $reasons[] = 'time';
            }
        }

        return $reasons;
    }

    public function isServiceDue(): bool
    {
        return count($this->serviceDueReasons()) > 0;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(MaintenanceHistory::class);
    }

    public function downtimes(): HasMany
    {
        return $this->hasMany(FleetDowntime::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(FleetComponent::class);
    }

    public function currentDowntime(): ?FleetDowntime
    {
        return $this->downtimes()->whereNull('ended_at')->latest('started_at')->first();
    }

    public function legalDocs(): HasMany
    {
        return $this->hasMany(FleetLegalDoc::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function operationalCosts(): HasMany
    {
        return $this->hasMany(OperationalCost::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(FleetRevenue::class);
    }
}
