<?php

namespace App\Modules\Maintenance\Services;

use App\Modules\Fleet\Services\FleetComponentTrackingService;
use App\Modules\Maintenance\Models\WorkOrder;
use App\Modules\Maintenance\Models\WorkOrderItem;
use App\Modules\MasterData\Models\Sparepart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logika penambahan item (sparepart/biaya) pada Work Order. Dua method
 * dengan efek yang SANGAT berbeda — jangan tertukar:
 * 1. planItem() — item PLAN/ESTIMASI saja, TIDAK menyentuh stok gudang.
 *    Dipakai RequestController::store() untuk payload `items` awal saat SA
 *    membuat pengajuan (murni rencana, belum tentu ini yang benar-benar
 *    dipakai).
 * 2. addItem() — item REALISASI, mengunci & mengurangi stok gudang
 *    sungguhan (FR-13) serta mencatat FleetComponentTrackingService. Dipakai
 *    WorkOrderController::storeItem() (endpoint tambahan manual) DAN
 *    WorkOrderController::realizeItems() (satu-satunya jalur realisasi
 *    sparepart pengajuan — lihat catatan di sana: stok baru berkurang di
 *    titik REALISASI, bukan saat pengajuan dibuat).
 */
class WorkOrderItemService
{
    public function __construct(private readonly FleetComponentTrackingService $componentTracking) {}

    /**
     * @param  array{sparepart_id?: int|null, description: string, qty: float, unit_cost: float}  $itemData
     */
    public function planItem(WorkOrder $workOrder, array $itemData): WorkOrderItem
    {
        $data = $itemData;
        $data['total_cost'] = $data['qty'] * $data['unit_cost'];

        // Konsisten dengan addItem(): vendor eksternal pakai sparepart
        // sendiri, jadi item rencana pun tidak tertaut ke katalog sparepart
        // gudang kita.
        if ($workOrder->execution_type !== 'internal') {
            $data['sparepart_id'] = null;
        }

        return $workOrder->items()->create($data);
    }

    /**
     * @param  array{sparepart_id?: int|null, description: string, qty: float, unit_cost: float}  $itemData
     */
    public function addItem(WorkOrder $workOrder, array $itemData): WorkOrderItem
    {
        $data = $itemData;
        $data['total_cost'] = $data['qty'] * $data['unit_cost'];

        // Sparepart gudang sendiri hanya relevan kalau pelaksananya mekanik
        // INTERNAL — vendor eksternal pakai sparepart/stok mereka sendiri,
        // jadi tidak boleh tertaut ke katalog sparepart & tidak memotong
        // stok gudang kita sama sekali, walau sparepart_id entah bagaimana
        // terkirim (mis. data basi). Untuk eksternal, item selalu
        // free-text (description/qty/unit_cost saja).
        if ($workOrder->execution_type !== 'internal') {
            $data['sparepart_id'] = null;
        }

        // FR-13: pemakaian sparepart pada Work Order otomatis mengurangi
        // stok gudang. Dikunci (lockForUpdate) supaya dua item WO paralel
        // untuk sparepart yang sama tidak balapan menghasilkan stok minus.
        return DB::transaction(function () use ($workOrder, $data) {
            if (! empty($data['sparepart_id'])) {
                $sparepart = Sparepart::query()->lockForUpdate()->findOrFail($data['sparepart_id']);
                $qtyUsed = (int) round($data['qty']);

                if ($sparepart->stock_qty < $qtyUsed) {
                    throw ValidationException::withMessages([
                        'qty' => "Stok {$sparepart->name} tidak mencukupi (tersisa {$sparepart->stock_qty}).",
                    ]);
                }

                $sparepart->decrement('stock_qty', $qtyUsed);

                // Manajemen ban & komponen major lain — baseline umur
                // komponen di-reset otomatis begitu sparepart kategori
                // terkait terpakai di WO ini (lihat FleetComponentTrackingService).
                if ($workOrder->request->fleet) {
                    $this->componentTracking->recordUsage($workOrder->request->fleet, $sparepart->category);
                }
            }

            return $workOrder->items()->create($data);
        });
    }
}
