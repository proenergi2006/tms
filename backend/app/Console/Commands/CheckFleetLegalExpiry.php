<?php

namespace App\Console\Commands;

use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Notifikasi legalitas armada mendekati jatuh tempo (FR-17 — PRD Bagian 6;
 * Architecture Document Bagian 4.5: "job terjadwal ... legalitas armada
 * mendekati jatuh tempo"). Dijadwalkan harian lewat routes/console.php.
 *
 * Dikirim ke Tim Logistik cabang armada terkait (fallback ke seluruh Tim
 * Logistik bila cabang tidak match — lihat NotificationService). Diulang
 * paling cepat seminggu sekali per dokumen agar tidak membanjiri notifikasi
 * selama masa mendekati jatuh tempo (default 30 hari).
 */
class CheckFleetLegalExpiry extends Command
{
    protected $signature = 'notifications:check-legal-expiry {--days=30 : Ambang hari sebelum jatuh tempo} {--remind-every=7 : Jarak minimal (hari) antar notifikasi untuk dokumen yang sama}';

    protected $description = 'Kirim notifikasi ke Tim Logistik untuk dokumen legalitas armada yang mendekati jatuh tempo';

    public function handle(NotificationService $notifications): int
    {
        $days = (int) $this->option('days');
        $remindEvery = (int) $this->option('remind-every');
        $totalSent = 0;
        $totalExpiring = 0;

        FleetLegalDoc::with('fleet')
            ->whereNotNull('expiry_date')
            ->get()
            ->each(function (FleetLegalDoc $doc) use ($notifications, $days, $remindEvery, &$totalSent, &$totalExpiring) {
                if (! $doc->fleet || ! $doc->isExpiringSoon($days)) {
                    return;
                }

                $totalExpiring++;

                // dedupNeedle harus literal substring dari $message di bawah,
                // karena alreadyNotifiedRecently() mencocokkan lewat SQL LIKE.
                $dedupNeedle = "{$doc->doc_type} armada {$doc->fleet->plate_number}";
                $message = "{$dedupNeedle} akan jatuh tempo pada {$doc->expiry_date->format('d M Y')}.";

                $totalSent += $notifications->notifyRoleOncePerWindow(
                    'tim_logistik',
                    'legal_expiry',
                    $message,
                    $dedupNeedle,
                    $remindEvery,
                    $doc->fleet->branch_id
                );
            });

        $this->info("Dokumen mendekati jatuh tempo: {$totalExpiring}. Notifikasi terkirim: {$totalSent}.");

        return self::SUCCESS;
    }
}
