<?php

namespace App\Modules\Fleet\Services;

use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetDowntime;
use App\Modules\Maintenance\Models\WorkOrder;

/**
 * Manajemen downtime armada — berapa lama unit tidak beroperasi karena
 * rusak (permintaan fitur eksplisit, lihat catatan batch di memori
 * project). Downtime dibuka saat pengajuan PERBAIKAN untuk armada tertentu
 * disubmit (RequestController::store()) dan ditutup saat WO tsb selesai
 * final (WorkOrderCompletionService::maybeFinalize()) atau ditolak
 * (ApprovalWorkflowService::reject()). fleets.status ikut disinkronkan
 * ('aktif' <-> 'maintenance') sebagai representasi "unit siap operasi" vs
 * "sedang di bengkel" — TIDAK menyentuh status 'nonaktif' (armada
 * dipensiunkan/dijual, di luar siklus downtime operasional).
 */
class FleetDowntimeService
{
    public function open(Fleet $fleet, WorkOrder $workOrder): void
    {
        // Idempoten — satu WO cuma boleh membuka satu window downtime.
        if (FleetDowntime::where('work_order_id', $workOrder->id)->exists()) {
            return;
        }

        FleetDowntime::create([
            'fleet_id' => $fleet->id,
            'work_order_id' => $workOrder->id,
            'started_at' => now(),
        ]);

        if ($fleet->status === 'aktif') {
            $fleet->update(['status' => 'maintenance']);
        }
    }

    public function close(Fleet $fleet, WorkOrder $workOrder): void
    {
        FleetDowntime::where('work_order_id', $workOrder->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        // Armada baru dianggap "siap operasi" lagi kalau TIDAK ADA window
        // downtime lain yang masih terbuka (mis. dua pengajuan perbaikan
        // beririsan) — mencegah status kembali 'aktif' padahal masih ada
        // perbaikan lain berjalan.
        $stillDown = FleetDowntime::where('fleet_id', $fleet->id)->whereNull('ended_at')->exists();
        if (! $stillDown && $fleet->status === 'maintenance') {
            $fleet->update(['status' => 'aktif']);
        }
    }

    /**
     * Dipakai RequestController::resubmit() — pengajuan yang ditolak lalu
     * diajukan ulang oleh SA memakai WorkOrder yang SAMA (bukan baru), jadi
     * open() (idempoten per work_order_id) akan diam saja kalau dipanggil
     * lagi di sini karena baris downtime-nya sudah ada (dari close() saat
     * ditolak). Method ini eksplisit membuka kembali window yang sama
     * (ended_at -> null) — armada memang belum pernah benar-benar selesai
     * diperbaiki, penolakan hanya menghentikan sementara di atas kertas.
     */
    public function reopen(Fleet $fleet, WorkOrder $workOrder): void
    {
        $downtime = FleetDowntime::where('work_order_id', $workOrder->id)->latest('started_at')->first();

        if ($downtime) {
            $downtime->update(['ended_at' => null]);
        } else {
            FleetDowntime::create([
                'fleet_id' => $fleet->id,
                'work_order_id' => $workOrder->id,
                'started_at' => now(),
            ]);
        }

        if ($fleet->status === 'aktif') {
            $fleet->update(['status' => 'maintenance']);
        }
    }
}
