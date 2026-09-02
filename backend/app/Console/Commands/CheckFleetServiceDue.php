<?php

namespace App\Console\Commands;

use App\Modules\Fleet\Models\Fleet;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Penjadwalan servis berkala otomatis berdasarkan interval km, jam operasi
 * mesin, atau waktu (whichever comes first — lihat Fleet::serviceDueReasons()),
 * beda dari inspeksi wajib 2 minggu (CheckFleetInspectionDue, cek rusak/tidak
 * tanpa syarat ambang). Notifikasi dikirim ke Tim Logistik dan Kepala Pool
 * (selaku kepala workshop) cabang armada terkait — permintaan fitur
 * eksplisit agar unit tidak "jebol di jalan". Sejak restrukturisasi peran,
 * mekanik & driver dihapus dari sistem; Tim Logistik (pelaksana Work Order)
 * adalah penerima operasional yang relevan menggantikan keduanya.
 * Dijadwalkan harian lewat routes/console.php.
 */
class CheckFleetServiceDue extends Command
{
    protected $signature = 'fleets:check-service-due {--remind-every=7 : Jarak minimal (hari) antar notifikasi untuk armada yang sama}';

    protected $description = 'Kirim notifikasi ke Tim Logistik dan Kepala Pool untuk armada yang jatuh tempo servis berkala';

    private const REASON_LABELS = [
        'km' => 'jarak tempuh',
        'engine_hours' => 'jam operasi mesin',
        'time' => 'waktu',
    ];

    public function handle(NotificationService $notifications): int
    {
        $remindEvery = (int) $this->option('remind-every');
        $totalDue = 0;
        $totalSent = 0;

        Fleet::query()
            ->where('status', '!=', 'nonaktif')
            ->where(function ($q) {
                $q->whereNotNull('service_interval_km')
                    ->orWhereNotNull('service_interval_engine_hours')
                    ->orWhereNotNull('service_interval_months');
            })
            ->get()
            ->each(function (Fleet $fleet) use ($notifications, $remindEvery, &$totalDue, &$totalSent) {
                $reasons = $fleet->serviceDueReasons();
                if (empty($reasons)) {
                    return;
                }

                $totalDue++;

                $reasonLabels = collect($reasons)->map(fn ($r) => self::REASON_LABELS[$r])->join(', ');
                $dedupNeedle = "armada {$fleet->plate_number} jatuh tempo servis berkala";
                $message = "{$dedupNeedle} ({$reasonLabels}).";

                foreach (['sa', 'kepala_pool'] as $role) {
                    $totalSent += $notifications->notifyRoleOncePerWindow(
                        $role,
                        'service_due',
                        $message,
                        $dedupNeedle,
                        $remindEvery,
                        $fleet->branch_id
                    );
                }
            });

        $this->info("Armada jatuh tempo servis berkala: {$totalDue}. Notifikasi terkirim: {$totalSent}.");

        return self::SUCCESS;
    }
}
