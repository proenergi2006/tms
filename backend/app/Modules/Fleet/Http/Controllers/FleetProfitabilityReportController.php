<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Exports\FleetProfitabilityExport;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetRevenue;
use App\Modules\Fleet\Models\OperationalCost;
use App\Modules\Maintenance\Models\MaintenanceHistory;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FleetProfitabilityReportController extends Controller
{
    /**
     * Ambang rasio biaya perbaikan kumulatif terhadap nilai perolehan armada
     * — rule-of-thumb umum di manajemen armada (biaya perbaikan sudah
     * separuh nilai unit → lebih murah ganti unit daripada terus diperbaiki).
     * Bukan keputusan otomatis, murni sinyal untuk dipertimbangkan Manajemen.
     */
    private const REPLACE_COST_RATIO_THRESHOLD = 0.5;

    public function index(Request $httpRequest)
    {
        return response()->json(['data' => $this->buildReport($httpRequest)]);
    }

    public function export(Request $httpRequest): BinaryFileResponse
    {
        $rows = $this->buildReport($httpRequest);

        return Excel::download(new FleetProfitabilityExport($rows), 'laporan-profitabilitas-armada.xlsx');
    }

    /**
     * Analisa biaya perawatan per unit untuk (1) mengidentifikasi armada
     * paling "boros" (diurutkan dari total biaya perbaikan tertinggi) dan
     * (2) membantu keputusan ganti vs pertahankan unit — biaya perbaikan
     * (maintenance_history, BUKAN operational_costs yang ikut mencampur
     * GPS/Asuransi/Cicilan) dibandingkan terhadap nilai perolehan armada
     * (fleets.purchase_price).
     */
    public function maintenanceCost(Request $httpRequest)
    {
        $branchId = $httpRequest->user()->isBranchScoped()
            ? $httpRequest->user()->branch_id
            : $httpRequest->query('branch_id');
        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        $repairCosts = MaintenanceHistory::query()
            ->when($periodFrom, fn ($q) => $q->where('performed_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('performed_at', '<=', "{$periodTo}-31"))
            ->selectRaw('fleet_id, SUM(cost) as total, COUNT(*) as repair_count')
            ->groupBy('fleet_id')
            ->get()
            ->keyBy('fleet_id');

        $fleets = Fleet::query()
            ->with('branch')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $rows = $fleets->map(function (Fleet $fleet) use ($repairCosts) {
            $repair = $repairCosts->get($fleet->id);
            $totalRepairCost = (float) ($repair->total ?? 0);
            $purchasePrice = $fleet->purchase_price !== null ? (float) $fleet->purchase_price : null;
            $costRatio = ($purchasePrice !== null && $purchasePrice > 0)
                ? round($totalRepairCost / $purchasePrice, 4)
                : null;

            return [
                'fleet_id' => $fleet->id,
                'plate_number' => $fleet->plate_number,
                'fleet_type' => $fleet->fleet_type,
                'branch' => $fleet->branch?->name,
                'purchase_price' => $purchasePrice,
                'total_repair_cost' => $totalRepairCost,
                'repair_count' => (int) ($repair->repair_count ?? 0),
                'cost_ratio' => $costRatio,
                'replace_recommended' => $costRatio !== null && $costRatio >= self::REPLACE_COST_RATIO_THRESHOLD,
            ];
        })
            ->sortByDesc('total_repair_cost')
            ->values();

        return response()->json(['data' => $rows]);
    }

    private function buildReport(Request $httpRequest): array
    {
        // Role bercabang (mis. Tim Logistik) hanya melihat laporan cabangnya
        // sendiri — mengabaikan ?branch_id= dari role lain. Finance/Manajemen
        // (Head Office) tetap bisa memfilter/melihat lintas-cabang.
        $branchId = $httpRequest->user()->isBranchScoped()
            ? $httpRequest->user()->branch_id
            : $httpRequest->query('branch_id');
        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        $costs = OperationalCost::query()
            ->when($periodFrom, fn ($q) => $q->where('incurred_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('incurred_at', '<=', "{$periodTo}-31"))
            ->selectRaw('fleet_id, SUM(amount) as total')
            ->groupBy('fleet_id')
            ->pluck('total', 'fleet_id');

        $revenues = FleetRevenue::query()
            ->when($periodFrom, fn ($q) => $q->where('period', '>=', $periodFrom))
            ->when($periodTo, fn ($q) => $q->where('period', '<=', $periodTo))
            ->selectRaw('fleet_id, SUM(amount) as total')
            ->groupBy('fleet_id')
            ->pluck('total', 'fleet_id');

        $fleets = Fleet::query()
            ->with('branch')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('plate_number')
            ->get();

        return $fleets->map(function (Fleet $fleet) use ($costs, $revenues) {
            $cost = (float) ($costs[$fleet->id] ?? 0);
            $revenue = (float) ($revenues[$fleet->id] ?? 0);

            return [
                'fleet_id' => $fleet->id,
                'plate_number' => $fleet->plate_number,
                'fleet_type' => $fleet->fleet_type,
                'branch' => $fleet->branch?->name,
                'total_cost' => $cost,
                'total_revenue' => $revenue,
                'profit' => $revenue - $cost,
            ];
        })->values()->all();
    }
}
