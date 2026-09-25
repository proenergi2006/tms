<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Modules\Fleet\Models\FleetRevenue;
use App\Modules\Fleet\Models\OperationalCost;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\Sparepart;
use Illuminate\Http\Request;

/**
 * Ringkasan lintas-cabang untuk Manajemen/Operational Manager — sebelumnya
 * semua laporan (profitabilitas, reliability, dst) cuma per-armada atau
 * agregat total perusahaan, tidak ada satu pun yang mengelompokkan per
 * cabang untuk membandingkan performa 7 cabang sekaligus dalam satu layar.
 */
class BranchComparisonReportController extends Controller
{
    /**
     * Jendela "akan jatuh tempo" yang sama dengan FleetController::legalWarnings()
     * (dashboard existing) — supaya definisi "mendekati jatuh tempo" konsisten
     * di seluruh aplikasi, bukan ambang baru yang beda sendiri di sini.
     */
    private const LEGAL_WARNING_DAYS = 30;

    public function index(Request $httpRequest)
    {
        $user = $httpRequest->user();
        $branchId = $user->isBranchScoped() ? $user->branch_id : $httpRequest->query('branch_id');
        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        $costs = OperationalCost::query()
            ->join('fleets', 'fleets.id', '=', 'operational_costs.fleet_id')
            ->when($branchId, fn ($q) => $q->where('fleets.branch_id', $branchId))
            ->when($periodFrom, fn ($q) => $q->where('incurred_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('incurred_at', '<=', "{$periodTo}-31"))
            ->selectRaw('fleets.branch_id as branch_id, SUM(operational_costs.amount) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $revenues = FleetRevenue::query()
            ->join('fleets', 'fleets.id', '=', 'fleet_revenues.fleet_id')
            ->when($branchId, fn ($q) => $q->where('fleets.branch_id', $branchId))
            ->when($periodFrom, fn ($q) => $q->where('fleet_revenues.period', '>=', $periodFrom))
            ->when($periodTo, fn ($q) => $q->where('fleet_revenues.period', '<=', $periodTo))
            ->selectRaw('fleets.branch_id as branch_id, SUM(fleet_revenues.amount) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        // Bukan pakai FleetLegalDoc::isExpiringSoon() (per-baris, butuh N query)
        // — satu query agregat per cabang, dua ember: sudah lewat jatuh tempo
        // (expired, belum diperbarui) vs akan jatuh tempo dalam
        // LEGAL_WARNING_DAYS hari ke depan (sama seperti fleets-legal-warnings).
        $legal = FleetLegalDoc::query()
            ->join('fleets', 'fleets.id', '=', 'fleet_legal_docs.fleet_id')
            ->when($branchId, fn ($q) => $q->where('fleets.branch_id', $branchId))
            ->selectRaw(
                'fleets.branch_id as branch_id, '.
                'SUM(CASE WHEN fleet_legal_docs.expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_count, '.
                'SUM(CASE WHEN fleet_legal_docs.expiry_date >= CURDATE() AND fleet_legal_docs.expiry_date <= DATE_ADD(CURDATE(), INTERVAL '.self::LEGAL_WARNING_DAYS.' DAY) THEN 1 ELSE 0 END) as expiring_count'
            )
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        // Sparepart tidak punya branch_id sendiri — cabangnya lewat gudang
        // (warehouse_id), sama seperti pola branch-scoping di SparepartController.
        $criticalStock = Sparepart::query()
            ->join('warehouses', 'warehouses.id', '=', 'spareparts.warehouse_id')
            ->when($branchId, fn ($q) => $q->where('warehouses.branch_id', $branchId))
            ->where('spareparts.status', 'order')
            ->selectRaw('warehouses.branch_id as branch_id, COUNT(*) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');

        $branches = Branch::query()
            ->when($branchId, fn ($q) => $q->where('id', $branchId))
            ->orderBy('name')
            ->get();

        $rows = $branches->map(function (Branch $branch) use ($costs, $revenues, $legal, $criticalStock) {
            $cost = (float) ($costs[$branch->id] ?? 0);
            $revenue = (float) ($revenues[$branch->id] ?? 0);
            $legalRow = $legal->get($branch->id);

            return [
                'branch_id' => $branch->id,
                'branch' => $branch->name,
                'total_cost' => $cost,
                'total_revenue' => $revenue,
                'profit' => $revenue - $cost,
                'legal_expired_count' => (int) ($legalRow->expired_count ?? 0),
                'legal_expiring_count' => (int) ($legalRow->expiring_count ?? 0),
                'critical_stock_count' => (int) ($criticalStock[$branch->id] ?? 0),
            ];
        })->sortBy('profit')->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * Tren profit/cost/revenue BULANAN seluruh perusahaan (atau satu cabang
     * kalau difilter) — bentuknya sengaja sama persis dengan breakdown
     * FleetHistoryController::profitability() (per-armada) supaya frontend
     * bisa pakai chart component yang sama, cuma skopnya company-wide.
     */
    public function trend(Request $httpRequest)
    {
        $user = $httpRequest->user();
        $branchId = $user->isBranchScoped() ? $user->branch_id : $httpRequest->query('branch_id');
        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        $costsByPeriod = OperationalCost::query()
            ->join('fleets', 'fleets.id', '=', 'operational_costs.fleet_id')
            ->when($branchId, fn ($q) => $q->where('fleets.branch_id', $branchId))
            ->when($periodFrom, fn ($q) => $q->where('incurred_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('incurred_at', '<=', "{$periodTo}-31"))
            ->selectRaw("DATE_FORMAT(incurred_at, '%Y-%m') as period, SUM(operational_costs.amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $revenueByPeriod = FleetRevenue::query()
            ->join('fleets', 'fleets.id', '=', 'fleet_revenues.fleet_id')
            ->when($branchId, fn ($q) => $q->where('fleets.branch_id', $branchId))
            ->when($periodFrom, fn ($q) => $q->where('fleet_revenues.period', '>=', $periodFrom))
            ->when($periodTo, fn ($q) => $q->where('fleet_revenues.period', '<=', $periodTo))
            ->selectRaw('fleet_revenues.period as period, SUM(fleet_revenues.amount) as total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $periods = $costsByPeriod->keys()->merge($revenueByPeriod->keys())->unique()->sort()->values();

        $breakdown = $periods->map(function ($period) use ($costsByPeriod, $revenueByPeriod) {
            $cost = (float) ($costsByPeriod[$period] ?? 0);
            $revenue = (float) ($revenueByPeriod[$period] ?? 0);

            return [
                'period' => $period,
                'total_cost' => $cost,
                'total_revenue' => $revenue,
                'profit' => $revenue - $cost,
            ];
        })->values();

        return response()->json(['data' => $breakdown]);
    }
}
