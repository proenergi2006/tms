<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\MechanicRequest;
use App\Modules\MasterData\Http\Resources\MechanicResource;
use App\Modules\MasterData\Models\Mechanic;
use Illuminate\Http\Request;

class MechanicController extends Controller
{
    public function index(Request $request)
    {
        $query = Mechanic::query()
            ->with('branch')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));

        if ($request->user()->isBranchScoped()) {
            $query->where('branch_id', $request->user()->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $mechanics = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return MechanicResource::collection($mechanics);
    }

    public function store(MechanicRequest $request)
    {
        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $mechanic = Mechanic::create([...$data, 'status' => $data['status'] ?? 'aktif']);

        return (new MechanicResource($mechanic))->response()->setStatusCode(201);
    }

    public function show(Mechanic $mechanic)
    {
        return new MechanicResource($mechanic->load('branch'));
    }

    public function update(MechanicRequest $request, Mechanic $mechanic)
    {
        if (! $request->user()->canAccessBranch($mechanic->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola mekanik cabang Anda sendiri.');
        }

        $data = $request->validated();
        if ($request->user()->isBranchScoped()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        $mechanic->update($data);

        return new MechanicResource($mechanic);
    }

    public function destroy(Request $request, Mechanic $mechanic)
    {
        if (! $request->user()->canAccessBranch($mechanic->branch_id)) {
            abort(403, 'Anda hanya dapat mengelola mekanik cabang Anda sendiri.');
        }

        $mechanic->delete();

        return response()->noContent();
    }
}
