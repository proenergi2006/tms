<?php

namespace App\Modules\SyopIntegration\Services;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\Driver;
use App\Modules\SyopIntegration\Contracts\SyopDataProviderInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Logika sinkronisasi armada/driver dari SYOP native — diekstrak dari
 * FleetController::syncFromSyop()/DriverController::syncFromSyop() supaya
 * bisa dipakai BERSAMA oleh dua jalur:
 * 1. Endpoint manual (POST fleets/sync-syop, drivers/sync-syop) — untuk
 *    pemicu ad-hoc per cabang oleh Tim Logistik/Fleet Operations/Admin
 *    Sistem (permission master-data.manage).
 * 2. Command terjadwal (SyncSyopMasterData) — jalan otomatis lintas SEMUA
 *    cabang tanpa perlu siapa pun mengklik tombol, supaya data armada/driver
 *    SUDAH tersinkron saat SA membuka form pengajuan (lihat catatan
 *    keputusan produk: sinkronisasi tidak boleh murni manual lagi).
 */
class SyopSyncService
{
    public function __construct(private readonly SyopDataProviderInterface $syop) {}

    /**
     * @return array{synced: int, skipped: int}
     */
    public function syncFleetsForBranch(Branch $branch): array
    {
        $eligible = $this->syop->getEligibleFleets()->where('branch_code', $branch->code);

        $synced = 0;
        $skipped = 0;
        foreach ($eligible as $row) {
            $fleet = Fleet::where('syop_fleet_id', $row->syop_id)->first();

            try {
                if ($fleet) {
                    $fleet->update([
                        'plate_number' => $row->plate_number,
                        'capacity' => $row->capacity,
                        'branch_id' => $branch->id,
                    ]);
                } else {
                    Fleet::create([
                        'syop_fleet_id' => $row->syop_id,
                        'plate_number' => $row->plate_number,
                        'capacity' => $row->capacity,
                        'branch_id' => $branch->id,
                        'fleet_type' => 'Belum diisi',
                        'status' => 'aktif',
                        'ownership' => 'milik_sendiri',
                        'mutation_status' => 'tidak_ada',
                    ]);
                }

                $synced++;
            } catch (UniqueConstraintViolationException) {
                // Nomor polisi ini sudah dipakai armada lain di TMS (mis.
                // dibuat manual sebelum sync, atau data SYOP dobel yang lolos
                // dari dedup di SyopNativeAdapter::getEligibleFleets()) —
                // dilewati, bukan menggagalkan seluruh proses sync.
                $skipped++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped];
    }

    /**
     * @return array{synced: int}
     */
    public function syncDriversForBranch(Branch $branch): array
    {
        $eligible = $this->syop->getEligibleDrivers()->where('branch_code', $branch->code);

        $synced = 0;
        foreach ($eligible as $row) {
            Driver::updateOrCreate(
                ['syop_driver_id' => $row->syop_id],
                [
                    'name' => $row->name,
                    'phone' => $row->phone,
                    'branch_id' => $branch->id,
                    'status' => 'aktif',
                ]
            );
            $synced++;
        }

        return ['synced' => $synced];
    }
}
