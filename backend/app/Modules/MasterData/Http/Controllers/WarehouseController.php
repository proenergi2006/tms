<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\WarehouseRequest;
use App\Modules\MasterData\Http\Resources\WarehouseResource;
use App\Modules\MasterData\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $query = Warehouse::query()->with('branch');

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $warehouses = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return WarehouseResource::collection($warehouses);
    }

    public function store(WarehouseRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $warehouse = Warehouse::create($data);

        return (new WarehouseResource($warehouse))->response()->setStatusCode(201);
    }

    public function show(Warehouse $warehouse)
    {
        return new WarehouseResource($warehouse->load('branch'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        if (! $request->user()->canAccessBranch($warehouse->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola gudang cabang Anda sendiri.');
        }

        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $warehouse->update($data);

        return new WarehouseResource($warehouse);
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        if (! $request->user()->canAccessBranch($warehouse->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola gudang cabang Anda sendiri.');
        }

        $warehouse->delete();

        return response()->noContent();
    }
}
