<?php

namespace App\Modules\Maintenance\Models;

use App\Models\User;
use App\Modules\Fleet\Models\Fleet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Request extends Model
{
    use HasFactory;

    protected $table = 'requests';

    protected $fillable = [
        'request_no', 'type', 'maintenance_nature', 'priority', 'fleet_id',
        'tar_no', 'diagnosis', 'estimated_days', 'odometer_km', 'trouble_date',
        'suggestion', 'action_taken', 'requested_by', 'description', 'status',
    ];

    protected $casts = [
        'trouble_date' => 'date:Y-m-d',
    ];

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Generator nomor pengajuan sederhana (FR-03). Bukan solusi aman untuk
     * konkurensi tinggi — pemanggil membungkusnya dalam transaksi DB.
     */
    public static function generateRequestNo(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;

        return sprintf('REQ-%d-%05d', $year, $count);
    }

    /**
     * No. TAR (Technical Analysis Report) — dibuat sistem, bukan diketik SA
     * (menghindari duplikasi/typo), hanya untuk pengajuan perbaikan. Dihitung
     * dari jumlah pengajuan type=perbaikan tahun berjalan, terpisah dari
     * penomoran request_no.
     */
    public static function generateTarNo(): string
    {
        $year = now()->year;
        $count = static::whereYear('created_at', $year)->where('type', 'perbaikan')->count() + 1;

        return sprintf('TAR-%d-%05d', $year, $count);
    }
}
