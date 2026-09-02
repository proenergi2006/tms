<?php

namespace App\Console\Commands;

use App\Modules\MasterData\Models\Branch;
use App\Modules\SyopIntegration\Services\SyopSyncService;
use Illuminate\Console\Command;

/**
 * Sinkronisasi armada & driver dari SYOP native untuk SEMUA cabang sekaligus
 * — dijadwalkan otomatis (lihat routes/console.php) supaya data armada/driver
 * TMS selalu mengikuti SYOP tanpa perlu ada yang mengklik tombol "Sinkronkan"
 * secara manual (keputusan produk: SA yang membuat pengajuan tidak boleh
 * melihat daftar armada yang basi/kosong hanya karena belum ada yang
 * men-sync). Endpoint manual (FleetController/DriverController::
 * syncFromSyop()) tetap tersedia untuk pemicu ad-hoc per cabang di luar
 * jadwal, memakai service yang sama (SyopSyncService) agar logikanya
 * konsisten satu sumber.
 */
class SyncSyopMasterData extends Command
{
    protected $signature = 'syop:sync-master-data';

    protected $description = 'Sinkronisasi armada & driver dari SYOP native untuk seluruh cabang';

    public function handle(SyopSyncService $syncService): int
    {
        $totalFleetsSynced = 0;
        $totalFleetsSkipped = 0;
        $totalDriversSynced = 0;

        foreach (Branch::all() as $branch) {
            $fleetResult = $syncService->syncFleetsForBranch($branch);
            $driverResult = $syncService->syncDriversForBranch($branch);

            $totalFleetsSynced += $fleetResult['synced'];
            $totalFleetsSkipped += $fleetResult['skipped'];
            $totalDriversSynced += $driverResult['synced'];

            $this->line("{$branch->code}: {$fleetResult['synced']} armada tersinkron ({$fleetResult['skipped']} dilewati), {$driverResult['synced']} driver tersinkron.");
        }

        $this->info("Selesai. Total: {$totalFleetsSynced} armada tersinkron ({$totalFleetsSkipped} dilewati), {$totalDriversSynced} driver tersinkron di ".Branch::count().' cabang.');

        return self::SUCCESS;
    }
}
