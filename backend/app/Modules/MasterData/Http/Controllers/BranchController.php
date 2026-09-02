<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\BranchRequest;
use App\Modules\MasterData\Http\Resources\BranchResource;
use App\Modules\MasterData\Models\Branch;

class BranchController extends Controller
{
    public function index()
    {
        return BranchResource::collection(Branch::orderBy('name')->paginate(request('per_page', 15)));
    }

    public function store(BranchRequest $request)
    {
        $branch = Branch::create($request->validated());

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch)
    {
        return new BranchResource($branch);
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->noContent();
    }
}
