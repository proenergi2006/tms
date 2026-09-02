<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\DriverRequest;
use App\Modules\MasterData\Http\Resources\DriverResource;
use App\Modules\MasterData\Models\Branch;
use App\Modules\MasterData\Models\Driver;
use App\Modules\SyopIntegration\Services\SyopSyncService;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $query = Driver::query()
            ->with(['branch', 'fleet'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $drivers = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return DriverResource::collection($drivers);
    }

    public function store(DriverRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $driver = Driver::create([...$data, 'status' => $data['status'] ?? 'aktif']);

        return (new DriverResource($driver))->response()->setStatusCode(201);
    }

    public function show(Driver $driver)
    {
        return new DriverResource($driver->load(['branch', 'fleet']));
    }

    public function update(DriverRequest $request, Driver $driver)
    {
        if (! $request->user()->canAccessBranch($driver->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola driver cabang Anda sendiri.');
        }

        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $driver->update($data);

        return new DriverResource($driver);
    }

    public function destroy(Request $request, Driver $driver)
    {
        if (! $request->user()->canAccessBranch($driver->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola driver cabang Anda sendiri.');
        }

        $driver->delete();

        return response()->noContent();
    }

    /**
     * Tarik driver dari SYOP native (pro_master_transportir_sopir) — hanya
     * yang berasal dari transportir Pro Energi sendiri atau TDS (lihat
     * SyopNativeAdapter::getEligibleDrivers()), disaring per cabang. Upsert
     * berdasar syop_driver_id supaya aman dijalankan berulang kali (tidak
     * membuat duplikat, memperbarui nama/telepon bila berubah di SYOP).
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
        $result = $syncService->syncDriversForBranch($branch);

        return response()->json(['data' => [...$result, 'branch' => $branch->code]]);
    }
}
