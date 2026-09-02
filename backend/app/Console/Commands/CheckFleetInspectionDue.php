<?php

namespace App\Console\Commands;

use App\Modules\Fleet\Models\Fleet;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Setiap armada wajib masuk workshop untuk dicek mekanik tiap 2 minggu, baik
 * rusak atau tidak (permintaan fitur — lihat catatan batch di memori
 * project). Dijadwalkan harian lewat routes/console.php, mengirim notifikasi
 * ke Kepala Pool cabang armada terkait (penanggung jawab pool/workshop —
 * PRD Bagian 4) begitu armada melewati ambang batas.
 */
class CheckFleetInspectionDue extends Command
{
    protected $signature = 'fleets:check-inspection-due {--remind-every=7 : Jarak minimal (hari) antar notifikasi untuk armada yang sama}';

    protected $description = 'Kirim notifikasi ke Kepala Pool untuk armada yang perlu masuk workshop (inspeksi rutin 2 minggu)';

    public function handle(NotificationService $notifications): int
    {
        $remindEvery = (int) $this->option('remind-every');
        $totalDue = 0;
        $totalSent = 0;

        Fleet::query()
            ->where('status', '!=', 'nonaktif')
            ->get()
            ->each(function (Fleet $fleet) use ($notifications, $remindEvery, &$totalDue, &$totalSent) {
                if (! $fleet->isInspectionDue()) {
                    return;
                }

                $totalDue++;

                $dedupNeedle = "armada {$fleet->plate_number} perlu masuk workshop";
                $lastChecked = $fleet->last_inspection_at?->format('d M Y') ?? 'belum pernah';
                $message = "{$dedupNeedle} untuk inspeksi rutin 2 minggu (terakhir diperiksa: {$lastChecked}).";

                $totalSent += $notifications->notifyRoleOncePerWindow(
                    'kepala_pool',
                    'inspection_due',
                    $message,
                    $dedupNeedle,
                    $remindEvery,
                    $fleet->branch_id
                );
            });

        $this->info("Armada perlu inspeksi: {$totalDue}. Notifikasi terkirim: {$totalSent}.");

        return self::SUCCESS;
    }
}
