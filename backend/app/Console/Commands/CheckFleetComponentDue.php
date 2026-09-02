<?php

namespace App\Console\Commands;

use App\Modules\Fleet\Models\FleetComponent;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Manajemen ban & komponen major lain (aki, oli, rem) — permintaan fitur
 * eksplisit. Beda dari CheckFleetServiceDue (satu jadwal servis umum per
 * armada): di sini tiap komponen dicek terpisah karena interval pakainya
 * jauh berbeda. Notifikasi ke Tim Logistik & Kepala Pool cabang armada
 * terkait — sejak restrukturisasi peran, mekanik dihapus dari sistem; Tim
 * Logistik yang menjalankan Work Order (start s/d selesai) adalah
 * penerima yang relevan menggantikannya.
 */
class CheckFleetComponentDue extends Command
{
    protected $signature = 'fleets:check-component-due {--remind-every=7 : Jarak minimal (hari) antar notifikasi untuk komponen yang sama}';

    protected $description = 'Kirim notifikasi ke Tim Logistik dan Kepala Pool untuk komponen armada (ban/aki/oli/rem) yang jatuh tempo diganti';

    private const COMPONENT_LABELS = [
        'ban' => 'ban',
        'oli_pelumas' => 'oli',
        'aki_kelistrikan' => 'aki',
        'rem' => 'rem',
    ];

    public function handle(NotificationService $notifications): int
    {
        $remindEvery = (int) $this->option('remind-every');
        $totalDue = 0;
        $totalSent = 0;

        FleetComponent::with('fleet')
            ->whereNotNull('fleet_id')
            ->where(function ($q) {
                $q->whereNotNull('interval_km')->orWhereNotNull('interval_months');
            })
            ->get()
            ->each(function (FleetComponent $component) use ($notifications, $remindEvery, &$totalDue, &$totalSent) {
                if (! $component->fleet || $component->fleet->status === 'nonaktif' || ! $component->isDue()) {
                    return;
                }

                $totalDue++;

                $label = self::COMPONENT_LABELS[$component->component_type] ?? $component->component_type;
                $dedupNeedle = "armada {$component->fleet->plate_number} perlu ganti {$label}";
                $message = "{$dedupNeedle}.";

                foreach (['sa', 'kepala_pool'] as $role) {
                    $totalSent += $notifications->notifyRoleOncePerWindow(
                        $role,
                        'component_due',
                        $message,
                        $dedupNeedle,
                        $remindEvery,
                        $component->fleet->branch_id
                    );
                }
            });

        $this->info("Komponen jatuh tempo: {$totalDue}. Notifikasi terkirim: {$totalSent}.");

        return self::SUCCESS;
    }
}
