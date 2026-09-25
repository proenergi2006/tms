<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan belanja sparepart untuk Manajemen/Operational Manager. Beda dari
 * Sparepart.unit_cost (harga acuan katalog) — ini pemakaian NYATA yang
 * terealisasi (work_order_items setelah WorkOrderController::realizeItems(),
 * ditandai items_realized_at). OperationalCost TIDAK bisa dipakai untuk ini:
 * WorkOrderCompletionService mencatatnya sebagai SATU angka gabungan per WO
 * (sparepart + jasa bercampur), bukan per kategori sparepart — jadi harus
 * query work_order_items langsung, join ke spareparts untuk kategorinya.
 */
class SparepartSpendReportController extends Controller
{
    public function index(Request $httpRequest)
    {
        $user = $httpRequest->user();
        $branchId = $user->isBranchScoped() ? $user->branch_id : $httpRequest->query('branch_id');
        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        // Cabang "konsumen": cabang armada yang diperbaiki, atau cabang
        // pengaju kalau pengajuan tidak terikat armada spesifik (restock
        // gudang) — sama seperti ApprovalController::branchId(). Item tanpa
        // sparepart_id (pekerjaan jasa/vendor eksternal free-text) otomatis
        // tidak ikut lewat inner join ke spareparts di bawah.
        $query = DB::table('work_order_items')
            ->join('work_orders', 'work_orders.id', '=', 'work_order_items.work_order_id')
            ->join('requests', 'requests.id', '=', 'work_orders.request_id')
            ->leftJoin('fleets', 'fleets.id', '=', 'requests.fleet_id')
            ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
            ->join('spareparts', 'spareparts.id', '=', 'work_order_items.sparepart_id')
            ->whereNotNull('work_orders.items_realized_at')
            ->when($periodFrom, fn ($q) => $q->where('work_orders.items_realized_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('work_orders.items_realized_at', '<=', "{$periodTo}-31"))
            ->when($branchId, fn ($q) => $q->whereRaw('COALESCE(fleets.branch_id, requesters.branch_id) = ?', [$branchId]));

        $byCategory = (clone $query)
            ->selectRaw('spareparts.category as category, SUM(work_order_items.total_cost) as total, SUM(work_order_items.qty) as qty')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $byBranchRaw = (clone $query)
            ->selectRaw('COALESCE(fleets.branch_id, requesters.branch_id) as branch_id, SUM(work_order_items.total_cost) as total')
            ->groupBy('branch_id')
            ->get();

        $branches = Branch::query()->whereIn('id', $byBranchRaw->pluck('branch_id')->unique()->filter())->pluck('name', 'id');

        $byBranch = $byBranchRaw->map(fn ($row) => [
            'branch_id' => $row->branch_id,
            'branch' => $branches->get($row->branch_id) ?? '-',
            'total' => (float) $row->total,
        ])->sortByDesc('total')->values();

        return response()->json([
            'data' => [
                'total_spend' => (float) $byCategory->sum('total'),
                'by_category' => $byCategory->map(fn ($row) => [
                    'category' => $row->category,
                    'total' => (float) $row->total,
                    'qty' => (float) $row->qty,
                ])->values(),
                'by_branch' => $byBranch,
            ],
        ]);
    }
}
