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

            // Belum pernah tersinkron dengan syop_fleet_id INI secara spesifik
            // — tapi armadanya mungkin SUDAH ada di TMS dengan syop_fleet_id
            // LAMA yang berbeda. Kasus nyata: SYOP sempat punya duplikat
            // nomor polisi terdaftar di dua transportir/cabang berbeda
            // (id_master beda), TMS ambil salah satu (lihat dedup di
            // SyopNativeAdapter::getEligibleFleets()), lalu tim SYOP
            // memperbaiki data itu belakangan sehingga id_master yang
            // eligible sekarang berbeda dari yang tersimpan di TMS. Re-link
            // ke baris TMS yang sudah ada (by plate_number) alih-alih
            // mencoba create baru dan bentrok unique constraint — SYOP tetap
            // satu-satunya sumber kebenaran, jadi TMS harus ikut pindah
            // cabang begitu data sumbernya benar, bukan "nyangkut" permanen
            // di cabang lama walau sudah di-resync berkali-kali.
            if (! $fleet) {
                $fleet = Fleet::where('plate_number', $row->plate_number)->first();
            }

            try {
                if ($fleet) {
                    $fleet->update([
                        'syop_fleet_id' => $row->syop_id,
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
                // Sisa kondisi residual yang masih mungkin lolos dari
                // penanganan re-link di atas (mis. race condition dua sync
                // berjalan bersamaan) — dilewati, bukan menggagalkan seluruh
                // proses sync.
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
            // 'phone' SENGAJA tidak diisi di sini — SYOP tidak punya data
            // nomor telepon sopir sama sekali (lihat catatan di
            // SyopNativeAdapter::getEligibleDrivers()). Kalau field ini
            // dipaksa null tiap sync, nomor yang sudah diisi manual lewat
            // Master Data TMS akan ke-timpa kosong lagi tiap jam — dengan
            // tidak menyertakan key ini, updateOrCreate() membiarkan nilai
            // phone yang sudah ada tetap utuh saat update.
            Driver::updateOrCreate(
                ['syop_driver_id' => $row->syop_id],
                [
                    'name' => $row->name,
                    'branch_id' => $branch->id,
                    'status' => 'aktif',
                ]
            );
            $synced++;
        }

        return ['synced' => $synced];
    }
}
