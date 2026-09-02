<?php

namespace App\Console\Commands;

use App\Modules\MasterData\Models\Sparepart;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Notifikasi stok sparepart di bawah ambang minimum (min_stock) — permintaan
 * fitur eksplisit ("minimum stock alert", lihat catatan batch di memori
 * project). Dikirim ke Tim Logistik cabang gudang terkait (sparepart tidak
 * punya branch_id sendiri, diturunkan dari warehouse — sama seperti
 * SparepartController::guardBranch()). Dijadwalkan harian lewat
 * routes/console.php.
 */
class CheckSparepartLowStock extends Command
{
    protected $signature = 'spareparts:check-low-stock {--remind-every=3 : Jarak minimal (hari) antar notifikasi untuk sparepart yang sama}';

    protected $description = 'Kirim notifikasi ke Tim Logistik untuk sparepart yang stoknya di bawah ambang minimum';

    public function handle(NotificationService $notifications): int
    {
        $remindEvery = (int) $this->option('remind-every');
        $totalLow = 0;
        $totalSent = 0;

        Sparepart::query()
            ->with('warehouse')
            ->whereColumn('stock_qty', '<', 'min_stock')
            ->get()
            ->each(function (Sparepart $sparepart) use ($notifications, $remindEvery, &$totalLow, &$totalSent) {
                if (! $sparepart->warehouse) {
                    return;
                }

                $totalLow++;

                $dedupNeedle = "stok {$sparepart->name} ({$sparepart->sku})";
                $message = "{$dedupNeedle} tersisa {$sparepart->stock_qty} {$sparepart->unit}, di bawah stok minimum ({$sparepart->min_stock} {$sparepart->unit}).";

                $totalSent += $notifications->notifyRoleOncePerWindow(
                    'tim_logistik',
                    'low_stock',
                    $message,
                    $dedupNeedle,
                    $remindEvery,
                    $sparepart->warehouse->branch_id
                );
            });

        $this->info("Sparepart di bawah stok minimum: {$totalLow}. Notifikasi terkirim: {$totalSent}.");

        return self::SUCCESS;
    }
}
