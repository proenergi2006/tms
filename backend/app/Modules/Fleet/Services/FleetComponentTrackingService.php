<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetComponent;

/**
 * Manajemen ban & komponen major lain (aki, oli, rem) — permintaan fitur
 * eksplisit. Baseline tiap komponen (last_replaced_at/odometer) diisi
 * OTOMATIS begitu Work Order memakai sparepart dengan kategori yang sesuai
 * (lihat WorkOrderController::storeItem()), sama seperti
 * FleetDowntimeService/last_service_* mengikuti event WO — supaya Tim
 * Logistik/Mekanik tidak perlu mencatat manual dua kali.
 */
class FleetComponentTrackingService
{
    private const TRACKED_CATEGORIES = ['ban', 'oli_pelumas', 'aki_kelistrikan', 'rem'];

    public function recordUsage(Fleet $fleet, ?string $sparepartCategory): void
    {
        // Case-insensitive: kolom spareparts.category adalah string bebas
        // (bukan enum DB), jadi data lama/input manual bisa saja tidak
        // persis lowercase seperti nilai dropdown terkontrol saat ini.
        $sparepartCategory = $sparepartCategory !== null ? strtolower($sparepartCategory) : null;

        if (! in_array($sparepartCategory, self::TRACKED_CATEGORIES, true)) {
            return;
        }

        $component = FleetComponent::firstOrNew(['fleet_id' => $fleet->id, 'component_type' => $sparepartCategory]);
        $component->last_replaced_at = now()->toDateString();
        // Jangan timpa dengan null bila armada belum punya pembacaan
        // odometer sama sekali — baseline km lebih baik "belum ada" daripada
        // salah/hilang.
        $component->last_replaced_odometer = $fleet->currentOdometer() ?? $component->last_replaced_odometer;
        $component->save();
    }
}
