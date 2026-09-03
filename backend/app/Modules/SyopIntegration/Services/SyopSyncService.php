<?php

namespace App\Modules\SyopIntegration\Services;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\Driver;
use App\Modules\SyopIntegration\Contracts\SyopDataProviderInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    /**
     * Toggle is_active armada di SYOP saat Work Order maintenance mulai
     * dikerjakan (nonaktif) / selesai (aktif kembali) — lihat
     * WorkOrderController::updateStatus(). Best-effort SENGAJA: gagal di
     * sini (grant MySQL belum diperluas, SYOP down, dst.) TIDAK BOLEH
     * menggagalkan update status WO itu sendiri, jadi exception ditangkap &
     * di-log sebagai warning, bukan dilempar ulang. Diam-diam no-op kalau
     * armada tidak punya syop_fleet_id (belum pernah tersinkron dari SYOP,//
     * atau request tanpa fleet, mis. restock gudang).
     */
    public function setFleetActiveInSyop(?Fleet $fleet, bool $isActive): void
    {
        if (! $fleet || ! $fleet->syop_fleet_id) {
            return;
        }

        try {
            $this->syop->setFleetActiveStatus($fleet->syop_fleet_id, $isActive);
        } catch (Throwable $e) {
            Log::warning("Gagal set is_active SYOP untuk armada {$fleet->plate_number} (syop_fleet_id={$fleet->syop_fleet_id}): {$e->getMessage()}");
        }
    }
}
