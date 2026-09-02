<?php

namespace App\Modules\Maintenance\Services;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\OperationalCost;
use App\Modules\Fleet\Services\FleetDowntimeService;
use App\Modules\Maintenance\Models\MaintenanceHistory;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\MasterData\Models\CostType;

/**
 * Finalisasi Work Order: mencatat riwayat perbaikan (maintenance_history) dan
 * biaya operasional (operational_cost) begitu pekerjaan SELESAI secara fisik
 * (status=finished) DAN sudah lolos seluruh tahap approval
 * (approval_status=completed — lihat Design Document Bagian 4.1). Gerbang
 * "Verifikasi Fleet Operations" pasca-selesai yang dulu ada di sini sudah
 * dihapus (redundan sejak Fleet Operations masuk ke rantai approval di
 * AWAL, lihat approval_steps) — finalisasi kini langsung terjadi begitu
 * kedua syarat di atas terpenuhi.
 *
 * Dipanggil dari dua tempat (ApprovalWorkflowService::approve(),
 * WorkOrderController::updateStatus()) karena kedua kondisi bisa tercapai
 * dalam urutan berbeda; idempoten terhadap panggilan berulang.
 */
class WorkOrderCompletionService
{
    public function __construct(private readonly FleetDowntimeService $downtimeService) {}

    public function maybeFinalize(WorkOrder $workOrder): void
    {
        $workOrder->refresh();

        if ($workOrder->approval_status !== 'completed' || $workOrder->status !== 'finished') {
            return;
        }

        $fleetId = $workOrder->request->fleet_id;
        $fleet = $fleetId ? Fleet::find($fleetId) : null;

        // Armada sudah masuk workshop dan diperiksa mekanik lewat WO ini —
        // ini juga memenuhi kewajiban pengecekan rutin 2 minggu (lihat
        // Fleet::isInspectionDue()) SEKALIGUS jadi baseline baru untuk
        // servis berkala berbasis km/jam-operasi/waktu (lihat
        // Fleet::serviceDueReasons()). Tidak menunggu verifikasi Fleet Ops:
        // itu murni audit laporan, bukan syarat terjadinya pemeriksaan fisik.
        $fleet?->update([
            'last_inspection_at' => now(),
            'last_service_at' => now()->toDateString(),
            'last_service_odometer' => $fleet->currentOdometer() ?? $fleet->last_service_odometer,
            'last_service_engine_hours' => $fleet->currentEngineHours() ?? $fleet->last_service_engine_hours,
        ]);

        // Dianggap "siap operasi" (downtime ditutup) begitu status fisik
        // 'finished' & approval_status 'completed' — sama seperti syarat
        // riwayat/biaya dicatat final di bawah.
        if ($fleet) {
            $this->downtimeService->close($fleet, $workOrder);
        }

        if (MaintenanceHistory::where('work_order_id', $workOrder->id)->exists()) {
            return;
        }

        if (! $fleetId) {
            // Pengajuan tanpa armada spesifik (mis. restock gudang) tidak
            // menghasilkan riwayat/biaya armada.
            return;
        }

        $cost = $workOrder->totalCost();

        MaintenanceHistory::create([
            'fleet_id' => $fleetId,
            'work_order_id' => $workOrder->id,
            'job_type_id' => null,
            'description' => $workOrder->request->description,
            'cost' => $cost,
            'performed_at' => now()->toDateString(),
        ]);

        if ($cost > 0) {
            $costType = CostType::firstOrCreate(
                ['name' => 'Perbaikan/Maintenance (Work Order)'],
                ['category' => 'operasional']
            );

            OperationalCost::create([
                'fleet_id' => $fleetId,
                'cost_type_id' => $costType->id,
                'amount' => $cost,
                'source_type' => 'work_order',
                'source_id' => $workOrder->id,
                'incurred_at' => now()->toDateString(),
            ]);
        }
    }
}
