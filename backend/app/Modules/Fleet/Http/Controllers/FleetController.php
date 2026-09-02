<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\FleetRequest;
use App\Modules\Fleet\Http\Resources\FleetResource;
use App\Modules\Fleet\Models\Fleet;
use App\Modules\Fleet\Models\FleetLegalDoc;
use App\Modules\Fleet\Services\FleetReliabilityService;
use App\Modules\MasterData\Models\Branch;
use App\Modules\SyopIntegration\Services\SyopSyncService;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    public function index(Request $request)
    {
        $query = Fleet::query()
            ->with('branch')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));

        // Role bercabang (Driver, Mekanik, Kepala Pool, BM, Tim Logistik)
        // hanya melihat armada cabangnya sendiri — lihat User::isBranchScoped().
        // Role Head Office tetap bisa memfilter lintas-cabang lewat ?branch_id=.
        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $fleets = $query->orderBy('plate_number')->paginate($request->integer('per_page', 15));

        return FleetResource::collection($fleets);
    }

    /**
     * Ringkasan status operasional armada untuk widget Dashboard: ready
     * (siap pakai), breakdown (dilaporkan rusak, menunggu masuk workshop),
     * service (sedang dikerjakan mekanik di workshop), nonaktif. Diturunkan
     * dari Request bertipe 'perbaikan' yang belum selesai + WorkOrder terkait
     * — bukan kolom terpisah, supaya selalu konsisten dengan alur
     * pengajuan/approval yang sudah ada (Design Document Bagian 4.1).
     */
    public function statusSummary(Request $request)
    {
        $query = Fleet::query()
            ->with([
                'branch:id,code,name',
                'requests' => fn ($q) => $q->where('type', 'perbaikan')
                    ->where('status', '!=', 'rejected')
                    ->with('workOrder:id,request_id,status,approval_status'),
            ]);

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $buckets = ['ready' => [], 'breakdown' => [], 'service' => [], 'nonaktif' => []];

        $query->orderBy('plate_number')->get()->each(function (Fleet $fleet) use (&$buckets) {
            $buckets[$this->operationalStatus($fleet)][] = [
                'id' => $fleet->id,
                'plate_number' => $fleet->plate_number,
                'branch' => $fleet->branch?->name,
            ];
        });

        return response()->json([
            'data' => $buckets,
            'counts' => array_map('count', $buckets),
        ]);
    }

    private function operationalStatus(Fleet $fleet): string
    {
        if ($fleet->status === 'nonaktif') {
            return 'nonaktif';
        }

        $workOrders = $fleet->requests->map->workOrder->filter();

        if ($workOrders->contains(fn ($wo) => $wo->status === 'on_progress')) {
            return 'service';
        }

        $hasOpenIssue = $fleet->requests->contains(
            fn ($req) => ! $req->workOrder
                || $req->workOrder->status !== 'finished'
                || $req->workOrder->approval_status !== 'completed'
        );

        return $hasOpenIssue ? 'breakdown' : 'ready';
    }

    /**
     * Dokumen legalitas armada yang mendekati jatuh tempo, untuk widget
     * Dashboard (FR-17). Dulunya frontend memanggil
     * GET /fleets/{id}/legal-docs SEKALI PER ARMADA (N+1) — tidak masalah
     * saat armada masih data demo (3 baris), tapi jadi request storm nyata
     * begitu sinkron SYOP mengisi >100 armada sungguhan (lihat
     * FleetController::syncFromSyop()) dan membanjiri PHP dev server
     * single-threaded. Satu query agregat di sini menggantikannya.
     */
    public function legalWarnings(Request $request)
    {
        $query = Fleet::query()->select('id', 'plate_number', 'branch_id');

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $plateByFleetId = $query->pluck('plate_number', 'id');
        $days = $request->integer('days', 30);

        $docs = FleetLegalDoc::whereIn('fleet_id', $plateByFleetId->keys())
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'data' => $docs->map(fn (FleetLegalDoc $doc) => [
                'id' => $doc->id,
                'fleet_id' => $doc->fleet_id,
                'fleet_plate' => $plateByFleetId->get($doc->fleet_id),
                'doc_type' => $doc->doc_type,
                'expiry_date' => $doc->expiry_date,
            ])->values(),
        ]);
    }

    /**
     * Availability rate & MTBF/MTTR lintas-armada untuk widget Dashboard
     * (Pelaporan & Analitik — lihat FleetReliabilityService untuk definisi
     * tiap metrik). Agregat fleet-wide dihitung dari total uptime/downtime/
     * kegagalan SEMUA armada gabung (bukan rata-rata dari rata-rata tiap
     * armada), supaya armada dengan riwayat lebih panjang/lebih banyak
     * kejadian tidak diperlakukan sama bobotnya dengan armada yang baru
     * terdaftar kemarin. Daftar per-armada diurutkan dari availability rate
     * TERENDAH — permukaan otomatis unit paling tidak andal.
     */
    public function reliabilitySummary(Request $request, FleetReliabilityService $reliability)
    {
        $query = Fleet::query()->with(['branch:id,code,name', 'downtimes']);

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $rows = $query->get()->map(function (Fleet $fleet) use ($reliability) {
            $metrics = $reliability->compute($fleet, $fleet->downtimes);

            return [
                'fleet_id' => $fleet->id,
                'plate_number' => $fleet->plate_number,
                'branch' => $fleet->branch?->name,
                ...$metrics,
            ];
        })->sortBy('availability_rate')->values();

        $totalUptime = $rows->sum('uptime_minutes');
        $totalPeriod = $rows->sum('period_minutes');
        $totalFailures = $rows->sum('failure_count');
        $totalCompletedRepairs = $rows->sum('completed_repair_count');
        $totalRepairMinutes = $rows->sum('total_repair_minutes');

        return response()->json([
            'data' => [
                'fleets' => $rows,
                'aggregate' => [
                    'availability_rate' => $totalPeriod > 0 ? round($totalUptime / $totalPeriod * 100, 2) : null,
                    'mtbf_minutes' => $totalFailures > 0 ? (int) round($totalUptime / $totalFailures) : null,
                    'mttr_minutes' => $totalCompletedRepairs > 0 ? (int) round($totalRepairMinutes / $totalCompletedRepairs) : null,
                ],
            ],
        ]);
    }

    /**
     * Tarik armada dari SYOP native (pro_master_transportir_mobil) — hanya
     * yang berasal dari transportir Pro Energi sendiri atau TDS (lihat
     * SyopNativeAdapter::getEligibleFleets()), disaring per cabang. Sama
     * seperti DriverController::syncFromSyop(): upsert berdasar
     * syop_fleet_id supaya aman dijalankan berulang. Field yang TIDAK ada
     * padanannya di SYOP (fleet_type, brand, model, dst — wajib diisi
     * manual oleh Tim Logistik) sengaja HANYA diisi placeholder saat armada
     * baru pertama kali dibuat, dan tidak pernah ditimpa lagi pada sync
     * berikutnya (beda dari plate_number/capacity yang memang milik SYOP).
     */
    public function syncFromSyop(Request $request, SyopSyncService $syncService)
    {
        if ($request->user()->isBranchScoped()) {
            $branchId = $request->user()->branch_id;
        } else {
            $request->validate(['branch_id' => ['required', 'exists:branches,id']]);
            $branchId = $request->integer('branch_id');
        }

        $branch = Branch::findOrFail($branchId);
        $result = $syncService->syncFleetsForBranch($branch);

        return response()->json(['data' => [...$result, 'branch' => $branch->code]]);
    }

    public function store(FleetRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        // status/ownership/mutation_status diisi eksplisit (bukan mengandalkan
        // DEFAULT DB) agar response langsung mencerminkan nilai sebenarnya
        // tanpa perlu reload.
        $fleet = Fleet::create([
            ...$data,
            'status' => $data['status'] ?? 'aktif',
            'ownership' => $data['ownership'] ?? 'milik_sendiri',
            'mutation_status' => $data['mutation_status'] ?? 'tidak_ada',
        ]);

        return (new FleetResource($fleet))->response()->setStatusCode(201);
    }

    public function show(Request $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat melihat armada cabang Anda sendiri.');
        }

        return new FleetResource($fleet->load('branch'));
    }

    public function update(FleetRequest $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola armada cabang Anda sendiri.');
        }

        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $fleet->update($data);

        return new FleetResource($fleet);
    }

    public function destroy(Request $request, Fleet $fleet)
    {
        if (! $request->user()->canAccessBranch($fleet->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola armada cabang Anda sendiri.');
        }

        $fleet->delete();

        return response()->noContent();
    }
}
