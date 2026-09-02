<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\FleetLegalDocRequest;
use App\Modules\Fleet\Http\Requests\FleetRevenueRequest;
use App\Modules\Fleet\Http\Requests\FuelLogRequest;
use App\Modules\Fleet\Http\Resources\FleetLegalDocResource;
use App\Modules\Fleet\Http\Resources\FleetRevenueResource;
use App\Modules\Fleet\Http\Resources\FuelLogResource;
use App\Modules\Fleet\Http\Resources\OperationalCostResource;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetComponent;
use App\Modules\Fleet\Models\FleetDowntime;
use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Modules\Fleet\Models\FleetRevenue;
use App\Modules\Fleet\Models\FuelLog;
use App\Modules\Fleet\Models\OperationalCost;
use App\Modules\Fleet\Services\FleetReliabilityService;
use App\Modules\Maintenance\Http\Resources\MaintenanceHistoryResource;
use App\Modules\Maintenance\Models\MaintenanceHistory;
use Illuminate\Http\Request;

class FleetHistoryController extends Controller
{
    /**
     * Semua endpoint di controller ini beroperasi pada satu armada spesifik —
     * role bercabang (Kepala Pool/BM/Tim Logistik/dst.) hanya boleh mengakses
     * riwayat/legalitas/fuel-log/biaya armada cabangnya sendiri. Lihat
     * User::canAccessBranch().
     */
    private function guardBranch(Request $request, Fleet $fleet): void
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat mengakses data armada cabang Anda sendiri.');
        }
    }

    public function maintenanceHistory(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        $history = $fleet->maintenanceHistory()
            ->with(['jobType', 'workOrder.mechanic', 'workOrder.vendor', 'workOrder.items.sparepart'])
            ->latest('performed_at')
            ->paginate($httpRequest->integer('per_page', 15));

        return MaintenanceHistoryResource::collection($history);
    }

    public function legalDocsIndex(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        return FleetLegalDocResource::collection($fleet->legalDocs()->orderBy('expiry_date')->get());
    }

    public function legalDocsStore(FleetLegalDocRequest $request, Fleet $fleet)
    {
        $this->guardBranch($request, $fleet);

        $doc = $fleet->legalDocs()->create($request->validated());

        return (new FleetLegalDocResource($doc))->response()->setStatusCode(201);
    }

    public function legalDocsUpdate(FleetLegalDocRequest $request, Fleet $fleet, FleetLegalDoc $legalDoc)
    {
        $this->guardBranch($request, $fleet);
        abort_unless($legalDoc->fleet_id === $fleet->id, 404);

        $legalDoc->update($request->validated());

        return new FleetLegalDocResource($legalDoc);
    }

    public function fuelLogsIndex(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        $logs = $fleet->fuelLogs()->latest('log_date')->paginate($httpRequest->integer('per_page', 15));

        return FuelLogResource::collection($logs);
    }

    public function fuelLogsStore(FuelLogRequest $request, Fleet $fleet)
    {
        $this->guardBranch($request, $fleet);

        $log = $fleet->fuelLogs()->create($request->validated());

        return (new FuelLogResource($log))->response()->setStatusCode(201);
    }

    public function operationalCosts(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        $costs = $fleet->operationalCosts()
            ->with('costType')
            ->when($httpRequest->filled('date_from'), fn ($q) => $q->where('incurred_at', '>=', $httpRequest->query('date_from')))
            ->when($httpRequest->filled('date_to'), fn ($q) => $q->where('incurred_at', '<=', $httpRequest->query('date_to')))
            ->latest('incurred_at')
            ->paginate($httpRequest->integer('per_page', 15));

        return OperationalCostResource::collection($costs);
    }

    /**
     * Input manual pendapatan armada — jalan pintas sementara sampai
     * `SyopNativeAdapter::getDataPerjalanan()`/`getPendapatan()` bisa
     * memetakan realisasi PO ke armada tertentu (skema `proenergi` belum
     * punya kolom penghubung, lihat catatan di adapter). Tanpa ini,
     * `fleet_revenues` selalu kosong dan Laporan Profitabilitas selalu
     * menampilkan pendapatan Rp 0.
     */
    public function revenueIndex(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        return FleetRevenueResource::collection($fleet->revenues()->latest('period')->get());
    }

    public function revenueStore(FleetRevenueRequest $request, Fleet $fleet)
    {
        $this->guardBranch($request, $fleet);

        $revenue = $fleet->revenues()->create([
            ...$request->validated(),
            'synced_at' => now(),
        ]);

        return (new FleetRevenueResource($revenue))->response()->setStatusCode(201);
    }

    public function profitability(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        $costsByPeriod = OperationalCost::where('fleet_id', $fleet->id)
            ->when($periodFrom, fn ($q) => $q->where('incurred_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('incurred_at', '<=', "{$periodTo}-31"))
            ->selectRaw("DATE_FORMAT(incurred_at, '%Y-%m') as period, SUM(amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $revenueByPeriod = FleetRevenue::where('fleet_id', $fleet->id)
            ->when($periodFrom, fn ($q) => $q->where('period', '>=', $periodFrom))
            ->when($periodTo, fn ($q) => $q->where('period', '<=', $periodTo))
            ->selectRaw('period, SUM(amount) as total')
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

        return response()->json([
            'data' => [
                'fleet_id' => $fleet->id,
                'summary' => [
                    'total_cost' => $breakdown->sum('total_cost'),
                    'total_revenue' => $breakdown->sum('total_revenue'),
                    'profit' => $breakdown->sum('profit'),
                ],
                'breakdown' => $breakdown,
            ],
        ]);
    }

    /**
     * Biaya perbaikan per kilometer, per bulan: biaya perbaikan (riwayat
     * maintenance_history, sumber yang sama dengan tab Riwayat) dibagi total
     * kilometer bulan tsb. Total kilometer diturunkan dari SELISIH ANTAR
     * PEMBACAAN ODOMETER YANG BERURUTAN pada fuel_logs (bukan cuma
     * min/max DALAM satu bulan) — supaya armada yang isi BBM cuma sekali
     * sebulan tetap bisa dihitung jaraknya (delta terhadap pembacaan
     * terakhir bulan sebelumnya), sepanjang datanya berurutan naik.
     * Selisih antara pembacaan N-1 dan N diatribusikan ke BULAN pembacaan
     * N (mis. isi BBM 30 Jun @ 10.000km lalu 15 Jul @ 11.500km -> 1.500km
     * masuk periode 2026-07, bukan 2026-06). Bulan tanpa pembacaan
     * odometer yang mengikutinya, atau tanpa pembacaan sebelumnya untuk
     * dibandingkan (pembacaan pertama dalam riwayat armada), tidak bisa
     * dihitung jaraknya (km_total = null) — bukan dianggap 0 km, supaya
     * tidak menyesatkan (pembagian dengan 0). Selisih negatif (odometer
     * turun — data salah input/direset) diabaikan (dianggap tidak valid,
     * bukan dihitung 0 atau negatif).
     */
    public function costPerKm(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        $periodFrom = $httpRequest->query('period_from');
        $periodTo = $httpRequest->query('period_to');

        $repairCostByPeriod = MaintenanceHistory::where('fleet_id', $fleet->id)
            ->when($periodFrom, fn ($q) => $q->where('performed_at', '>=', "{$periodFrom}-01"))
            ->when($periodTo, fn ($q) => $q->where('performed_at', '<=', "{$periodTo}-31"))
            ->selectRaw("DATE_FORMAT(performed_at, '%Y-%m') as period, SUM(cost) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        // Diambil TANPA batas periode dulu: pembacaan sebelum period_from
        // tetap dibutuhkan sebagai baseline delta untuk pembacaan pertama
        // DALAM rentang periode.
        $readings = FuelLog::where('fleet_id', $fleet->id)
            ->whereNotNull('odometer')
            ->orderBy('log_date')
            ->orderBy('id')
            ->get(['log_date', 'odometer']);

        $kmByPeriod = [];
        $previous = null;
        foreach ($readings as $reading) {
            if ($previous !== null) {
                $delta = $reading->odometer - $previous->odometer;
                if ($delta >= 0) {
                    $period = $reading->log_date->format('Y-m');
                    $kmByPeriod[$period] = ($kmByPeriod[$period] ?? 0) + $delta;
                }
            }
            $previous = $reading;
        }

        $kmByPeriod = collect($kmByPeriod)
            ->when($periodFrom, fn ($c) => $c->filter(fn ($v, $period) => $period >= $periodFrom))
            ->when($periodTo, fn ($c) => $c->filter(fn ($v, $period) => $period <= $periodTo));

        $periods = $repairCostByPeriod->keys()->merge($kmByPeriod->keys())->unique()->sort()->values();

        $breakdown = $periods->map(function ($period) use ($repairCostByPeriod, $kmByPeriod) {
            $cost = (float) ($repairCostByPeriod[$period] ?? 0);
            $km = $kmByPeriod->get($period);

            return [
                'period' => $period,
                'repair_cost' => $cost,
                'km_total' => $km,
                'cost_per_km' => ($km !== null && $km > 0) ? round($cost / $km, 2) : null,
            ];
        })->values();

        return response()->json(['data' => ['fleet_id' => $fleet->id, 'breakdown' => $breakdown]]);
    }

    /**
     * Riwayat & ringkasan downtime — berapa lama armada tidak beroperasi
     * karena rusak (lihat FleetDowntimeService). Window yang masih berjalan
     * (ended_at null) tetap dihitung durasinya s/d sekarang untuk ringkasan,
     * supaya downtime yang sedang terjadi tidak "hilang" dari total.
     */
    public function downtimes(Request $httpRequest, Fleet $fleet, FleetReliabilityService $reliability)
    {
        $this->guardBranch($httpRequest, $fleet);

        $records = $fleet->downtimes()
            ->with('workOrder.request')
            ->latest('started_at')
            ->get();

        $monthStart = now()->startOfMonth();
        $totalMinutesThisMonth = $records
            ->filter(fn (FleetDowntime $d) => ($d->ended_at ?? now())->gte($monthStart))
            ->sum(fn (FleetDowntime $d) => (int) round($d->started_at->max($monthStart)->diffInMinutes($d->ended_at ?? now())));

        $metrics = $reliability->compute($fleet, $records);

        return response()->json([
            'data' => [
                'fleet_id' => $fleet->id,
                'currently_down' => $records->contains(fn ($d) => $d->ended_at === null),
                'records' => $records->map(fn (FleetDowntime $d) => [
                    'id' => $d->id,
                    'started_at' => $d->started_at,
                    'ended_at' => $d->ended_at,
                    'duration_minutes' => (int) round($d->started_at->diffInMinutes($d->ended_at ?? now())),
                    'wo_no' => $d->workOrder?->wo_no,
                    'description' => $d->workOrder?->request?->description,
                ])->values(),
                'summary' => [
                    'total_downtime_minutes' => $metrics['total_downtime_minutes'],
                    'total_downtime_minutes_this_month' => $totalMinutesThisMonth,
                    'count' => $records->count(),
                    'availability_rate' => $metrics['availability_rate'],
                    'mttr_minutes' => $metrics['mttr_minutes'],
                    'mtbf_minutes' => $metrics['mtbf_minutes'],
                ],
            ],
        ]);
    }

    private const TRACKED_COMPONENT_TYPES = ['ban', 'oli_pelumas', 'aki_kelistrikan', 'rem'];

    /**
     * Manajemen ban & komponen major lain (aki, oli, rem) — permintaan fitur
     * eksplisit. Selalu kembalikan 4 baris (satu per komponen) walau belum
     * pernah dikonfigurasi/diganti sama sekali, supaya UI tidak perlu
     * menangani "belum ada data" secara berbeda dari "sudah ada tapi 0".
     */
    public function componentsIndex(Request $httpRequest, Fleet $fleet)
    {
        $this->guardBranch($httpRequest, $fleet);

        $existing = $fleet->components()->get()->keyBy('component_type');

        $rows = collect(self::TRACKED_COMPONENT_TYPES)->map(function ($type) use ($fleet, $existing) {
            $component = $existing->get($type) ?? new FleetComponent(['fleet_id' => $fleet->id, 'component_type' => $type]);
            $component->setRelation('fleet', $fleet);

            return [
                'component_type' => $type,
                'last_replaced_at' => $component->last_replaced_at,
                'last_replaced_odometer' => $component->last_replaced_odometer,
                'interval_km' => $component->interval_km,
                'interval_months' => $component->interval_months,
                'notes' => $component->notes,
                'due' => $component->isDue(),
                'due_reasons' => $component->dueReasons(),
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function componentsUpdate(Request $httpRequest, Fleet $fleet, string $componentType)
    {
        $this->guardBranch($httpRequest, $fleet);
        abort_unless(in_array($componentType, self::TRACKED_COMPONENT_TYPES, true), 404);

        $data = $httpRequest->validate([
            'interval_km' => ['nullable', 'integer', 'min:1'],
            'interval_months' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $component = FleetComponent::updateOrCreate(
            ['fleet_id' => $fleet->id, 'component_type' => $componentType],
            $data
        );

        return response()->json(['data' => $component]);
    }

    /**
     * Pencatatan manual bila komponen diganti di luar alur Work Order formal
     * (mis. driver ganti ban sendiri di jalan) — pelengkap dari pencatatan
     * otomatis lewat WorkOrderController::storeItem().
     */
    public function componentsMarkReplaced(Request $httpRequest, Fleet $fleet, string $componentType)
    {
        $this->guardBranch($httpRequest, $fleet);
        abort_unless(in_array($componentType, self::TRACKED_COMPONENT_TYPES, true), 404);

        $component = FleetComponent::updateOrCreate(
            ['fleet_id' => $fleet->id, 'component_type' => $componentType],
            [
                'last_replaced_at' => now()->toDateString(),
                'last_replaced_odometer' => $fleet->currentOdometer(),
            ]
        );

        return response()->json(['data' => $component]);
    }
}
